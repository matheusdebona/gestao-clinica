<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\HofPayServer;
use App\Mcp\Support\Fmt;
use App\Mcp\Support\McpToolException;
use App\Mcp\Support\Pii;
use App\Mcp\Support\ReadOnlyApi;
use App\Mcp\Tools\HofPayTool;
use App\Models\McpAuditLog;
use App\Models\McpOAuthUser;
use App\Models\User;
use App\Support\ClinicMonth;
use App\Support\CurrentClinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;
use Tests\TestCase;

/**
 * Ferramentas do conector MCP (Claude): só leitura, números iguais aos do app,
 * isolamento entre clínicas, permissões e nenhum telefone completo nas respostas.
 */
class McpToolsTest extends TestCase
{
    use McpFixtures, RefreshDatabase;

    /** Chamada de cada ferramenta com argumentos válidos. */
    private const CALLS = [
        'resumo_faturamento' => [],
        'vendas_por_produto' => [],
        'dre_do_mes' => [],
        'agenda' => [],
        'leads' => ['mes' => null],
        'buscar_pacientes' => [],
        'paciente_detalhe' => ['id' => null],
        'metas_do_mes' => [],
        'estoque_baixo' => [],
        'retornos_pendentes' => [],
        'orcamentos' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMcpFixtures();
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function rpc(User $user, string $method, array $params = []): array
    {
        Passport::actingAs(McpOAuthUser::query()->findOrFail($user->id), ['mcp:read']);

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], [
            'Accept' => 'application/json, text/event-stream',
        ])->assertOk()->json();
    }

    /** @return array{text: string, error: bool} */
    private function callTool(User $user, string $tool, array $args = []): array
    {
        $result = $this->rpc($user, 'tools/call', ['name' => $tool, 'arguments' => (object) $args])['result'];

        return ['text' => collect($result['content'])->pluck('text')->implode("\n"), 'error' => (bool) ($result['isError'] ?? false)];
    }

    private function argsFor(string $tool, User $user): array
    {
        return match ($tool) {
            'leads' => ['mes' => substr(ClinicMonth::of(), 0, 7)],
            'paciente_detalhe' => ['id' => $user->clinic_id === $this->clinicA->id ? $this->patientA->id : $this->patientB->id],
            default => self::CALLS[$tool],
        };
    }

    public function test_tool_list_is_exactly_the_read_only_catalog(): void
    {
        $tools = $this->rpc($this->adminA, 'tools/list')['result']['tools'];
        $this->assertEqualsCanonicalizing(array_keys(self::CALLS), array_column($tools, 'name'));

        foreach ($tools as $tool) {
            $this->assertTrue($tool['annotations']['readOnlyHint'], $tool['name'].' sem readOnlyHint');
            $this->assertFalse($tool['annotations']['destructiveHint'], $tool['name']);
            $this->assertDoesNotMatchRegularExpression('/(cria|cadastr|edit|alter|atualiz|apag|exclu|remov|envi|create|update|delete|send|write|set_)/i', $tool['name']);
        }

        // Toda ferramenta registrada estende a base só leitura (anotações fixas + auditoria).
        $registered = (new ReflectionClass(HofPayServer::class))->getProperty('tools')->getDefaultValue();
        foreach ($registered as $class) {
            $this->assertTrue(is_subclass_of($class, HofPayTool::class), $class);
        }
        $this->assertSame([], $this->rpc($this->adminA, 'resources/list')['result']['resources']);
        $this->assertSame([], $this->rpc($this->adminA, 'prompts/list')['result']['prompts']);
    }

    public function test_every_tool_answers_without_writing_anything(): void
    {
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|alter|create|drop|truncate)\b/i', $query->sql) === 1
                && ! str_contains($query->sql, 'mcp_audit_logs')) {
                $writes[] = $query->sql;
            }
        });

        foreach (array_keys(self::CALLS) as $tool) {
            $r = $this->callTool($this->adminA, $tool, $this->argsFor($tool, $this->adminA));
            $this->assertFalse($r['error'], $tool.': '.$r['text']);
            $this->assertNotSame('', trim($r['text']), $tool);
            if (getenv('MCP_DUMP')) {
                file_put_contents(getenv('MCP_DUMP'), "\n\n===== {$tool}\n".$r['text'], FILE_APPEND);
            }
        }

        $this->assertSame([], $writes, 'Ferramentas MCP não podem escrever no banco.');
        $this->assertSame(count(self::CALLS), McpAuditLog::query()->withoutGlobalScopes()->where('status', 'ok')->count());
    }

    public function test_no_full_phone_email_or_clinical_note_in_any_response(): void
    {
        foreach ([$this->adminA, $this->adminB, $this->managerNoFinance] as $user) {
            foreach (array_keys(self::CALLS) as $tool) {
                $text = $this->callTool($user, $tool, $this->argsFor($tool, $user))['text'];
                $digits = preg_replace('/\D+/', '', $text);

                foreach ($this->phones as $phone) {
                    $this->assertStringNotContainsString($phone, $digits, "{$tool}: telefone completo vazou");
                }
                $this->assertDoesNotMatchRegularExpression(Pii::PHONE_REGEX, $text, "{$tool}: algo com cara de telefone");
                $this->assertDoesNotMatchRegularExpression(Pii::EMAIL_REGEX, $text, "{$tool}: e-mail");
                $this->assertStringNotContainsString('lidocaína', $text, "{$tool}: anotação clínica");
                $this->assertStringNotContainsString('Rugas na testa', $text, $tool);
                $this->assertStringNotContainsString('Prefere manhã', $text, $tool);
            }
        }
    }

    public function test_searches_by_phone_or_digits_are_refused(): void
    {
        foreach ([['buscar_pacientes', 'busca'], ['leads', 'busca'], ['agenda', 'paciente']] as [$tool, $arg]) {
            foreach (['11987650001', '0001', '98765', 'ana@alfa.test'] as $term) {
                $r = $this->callTool($this->adminA, $tool, [$arg => $term]);
                $this->assertTrue($r['error'], "{$tool} aceitou {$term}");
                $this->assertStringContainsString('só nome', $r['text']);
            }
        }
        $this->assertTrue(str_contains($this->callTool($this->adminA, 'buscar_pacientes', ['busca' => 'Paula'])['text'], 'Paula Alfa'));
    }

    public function test_numbers_match_the_app_endpoints(): void
    {
        $month = substr(ClinicMonth::of(), 0, 7);
        $from = $month.'-01';
        $to = ClinicMonth::todayDate();
        Sanctum::actingAs($this->adminA);
        $revenue = $this->getJson("/api/v1/metrics/commercial?from={$from}&to={$to}")->json('data.kpis.revenue');
        $net = $this->getJson("/api/v1/finance/dre?month={$month}")->json('data.current.lines.net_result');
        $this->assertSame('1500.00', $revenue);

        $this->assertStringContainsString('**R$ 1.500,00**', $this->callTool($this->adminA, 'resumo_faturamento')['text']);
        $this->assertStringContainsString('**R$ 1.500,00**', $this->callTool($this->adminA, 'vendas_por_produto')['text']);
        $dre = $this->callTool($this->adminA, 'dre_do_mes')['text'];
        $this->assertStringContainsString('| Receita bruta | R$ 1.500,00 |', $dre);
        $this->assertStringContainsString(Fmt::brl($net), $dre);
    }

    public function test_clinic_b_never_sees_clinic_a(): void
    {
        foreach (array_keys(self::CALLS) as $tool) {
            $text = $this->callTool($this->adminB, $tool, $this->argsFor($tool, $this->adminB))['text'];
            $this->assertStringNotContainsString('Alfa', $text, "{$tool} mostrou dado da clínica A para B");
            $this->assertStringNotContainsString('1.500,00', $text, $tool);
        }
        $this->assertStringContainsString('9.999,00', $this->callTool($this->adminB, 'resumo_faturamento')['text']);

        // Id de paciente de outra clínica = não encontrado.
        $r = $this->callTool($this->adminB, 'paciente_detalhe', ['id' => $this->patientA->id]);
        $this->assertTrue($r['error']);
        $this->assertStringContainsString('Não encontrado', $r['text']);
        $this->assertStringNotContainsString('Paula Alfa', $r['text']);
    }

    public function test_user_without_finance_view_cannot_see_revenue_dre_or_pay(): void
    {
        foreach (['resumo_faturamento', 'dre_do_mes', 'vendas_por_produto'] as $tool) {
            $r = $this->callTool($this->managerNoFinance, $tool);
            $this->assertTrue($r['error'], $tool);
            $this->assertStringContainsString('não tem permissão', $r['text']);
            $this->assertStringNotContainsString('1.500,00', $r['text']);
        }

        $metas = $this->callTool($this->managerNoFinance, 'metas_do_mes')['text'];
        $this->assertStringContainsString('não aparece', $metas);
        $this->assertStringNotContainsString('Remuneração variável', $metas);

        // Sem sales.view: ficha do paciente sem compras nem total comprado.
        $detail = $this->callTool($this->managerNoFinance, 'paciente_detalhe', ['id' => $this->patientA->id])['text'];
        $this->assertStringContainsString('Paula Alfa', $detail);
        $this->assertStringNotContainsString('Compras', $detail);
        $this->assertStringNotContainsString('1.500,00', $detail);
    }

    public function test_invalid_arguments_get_portuguese_errors_without_internals(): void
    {
        $r = $this->callTool($this->adminA, 'dre_do_mes', ['mes' => '2026-13']);
        $this->assertTrue($r['error']);
        $this->assertStringContainsString('AAAA-MM', $r['text']);

        $r = $this->callTool($this->adminA, 'agenda', ['de' => '2026-01-01', 'ate' => '2026-03-01']);
        $this->assertStringContainsString('31 dias', $r['text']);

        $r = $this->callTool($this->adminA, 'paciente_detalhe', ['id' => 999999]);
        $this->assertTrue($r['error']);
        $this->assertDoesNotMatchRegularExpression('/SQL|Exception|vendor|\.php|stack/i', $r['text']);
    }

    public function test_audit_log_records_who_and_which_tool_without_pii(): void
    {
        $this->callTool($this->adminA, 'buscar_pacientes', ['busca' => 'Paula']);
        $this->callTool($this->adminA, 'buscar_pacientes', ['busca' => '11987650001']);
        $this->callTool($this->adminA, 'buscar_pacientes', ['busca' => 11987650001]);

        $logs = McpAuditLog::query()->withoutGlobalScopes()->orderBy('id')->get();
        $this->assertCount(3, $logs);
        $this->assertSame($this->adminA->id, $logs[0]->user_id);
        $this->assertSame($this->clinicA->id, $logs[0]->clinic_id);
        $this->assertSame('buscar_pacientes', $logs[0]->tool);
        $this->assertSame(['busca' => 'P***'], $logs[0]->arguments);
        $this->assertSame('ok', $logs[0]->status);
        $this->assertSame('error', $logs[1]->status);
        $this->assertSame(['busca' => '1***'], $logs[2]->arguments);
        $this->assertStringNotContainsString('11987650001', json_encode($logs->toArray()));
    }

    public function test_audit_sanitizer_masks_numbers_strings_and_unknown_keys(): void
    {
        $clean = HofPayTool::sanitizeArguments([
            'busca' => 11987650001,
            'paciente' => 'Paula Alfa',
            'id' => 12,
            'mes' => '2026-10',
            'extra' => 'ligar para (11) 98765-0001 ou ana@alfa.test',
            'numero' => 11987650001,
            'lista' => ['11987650001'],
        ]);
        $this->assertSame('1***', $clean['busca']);
        $this->assertSame('P***', $clean['paciente']);
        $this->assertSame(12, $clean['id']);
        $this->assertSame('2026-10', $clean['mes']);
        $this->assertArrayNotHasKey('lista', $clean);
        $json = json_encode($clean);
        $this->assertStringNotContainsString('98765', preg_replace('/\D/', '', (string) $clean['extra']).'');
        $this->assertStringNotContainsString('ana@alfa.test', $json);
        $this->assertStringNotContainsString('11987650001', $json);
    }

    public function test_a_read_endpoint_that_tries_to_write_is_blocked_and_rolled_back(): void
    {
        // Simula um GET "de leitura" que escreve: no Postgres a transação READ ONLY
        // recusa; em qualquer banco o rollback desfaz.
        Route::get('api/v1/clinics/current', function () {
            DB::table('clinics')->update(['name' => 'HACKED']);

            return response()->json(['data' => ['ok' => true]]);
        });

        CurrentClinic::setId($this->clinicA->id);
        $api = app(ReadOnlyApi::class);
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                $api->get($this->adminA, 'clinics/current');
                $this->fail('Escrita deveria falhar na transação READ ONLY.');
            } catch (McpToolException $e) {
                $this->assertStringContainsString('Não foi possível consultar', $e->getMessage());
            }
        } else {
            $api->get($this->adminA, 'clinics/current');
        }

        $this->assertSame(0, DB::table('clinics')->where('name', 'HACKED')->count());
        $this->assertSame('Clínica Alfa', $this->clinicA->fresh()->name);
    }

    public function test_read_only_api_refuses_paths_outside_the_allowlist(): void
    {
        CurrentClinic::setId($this->clinicA->id);
        $this->expectExceptionMessage('Consulta não permitida.');
        app(ReadOnlyApi::class)->get($this->adminA, 'users');
    }
}
