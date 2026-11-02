<?php

namespace App\Mcp\Tools;

use App\Http\Middleware\EnsureMcpUser;
use App\Mcp\Support\McpToolException;
use App\Mcp\Support\Pii;
use App\Mcp\Support\ReadOnlyApi;
use App\Models\McpAuditLog;
use App\Models\User;
use App\Support\ClinicMonth;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * Base das ferramentas do HOF Pay no Claude. Todas são só leitura:
 *  - as anotações são fixas aqui (readOnlyHint=true, destructiveHint=false);
 *  - os dados vêm do ReadOnlyApi (só GET, transação sempre desfeita);
 *  - cada chamada fica registrada em mcp_audit_logs (quem, qual ferramenta, quando).
 */
abstract class HofPayTool extends Tool
{
    /** Período máximo aceito numa consulta (dias). */
    protected const MAX_RANGE_DAYS = 366;

    /** Parâmetros de busca por nome (mascarados no registro de auditoria). */
    public const NAME_SEARCH_ARGUMENTS = ['busca', 'paciente', 'produto'];

    /** Ferramentas nunca alteram dados: anotações fixas para todas. */
    public function annotations(): array
    {
        return [
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint' => false,
        ];
    }

    /** Monta o texto da resposta (Markdown, em português). */
    abstract protected function run(Request $request, ReadOnlyApi $api, User $user): string;

    final public function handle(Request $request, ReadOnlyApi $api): Response
    {
        $user = $request->user();
        $started = hrtime(true);
        $status = 'ok';
        $error = null;

        try {
            if (! $user instanceof User || $user->clinic_id === null) {
                throw new McpToolException('Conecte sua conta do HOF Pay para consultar.');
            }

            // Última barreira de PII: nenhum telefone, e-mail ou CPF completo sai do HOF Pay.
            $response = Response::text(Pii::scrub($this->run($request, $api, $user)));
        } catch (McpToolException $e) {
            [$status, $error] = ['error', $e->getMessage()];
            $response = Response::error(Pii::scrub($e->getMessage()));
        } catch (ValidationException $e) {
            $message = Pii::scrub((string) (collect($e->errors())->flatten()->first() ?? 'Parâmetros inválidos.'));
            [$status, $error] = ['error', $message];
            $response = Response::error($message);
        } catch (Throwable $e) {
            report($e);
            [$status, $error] = ['error', 'erro interno'];
            $response = Response::error('Não foi possível consultar agora. Tente de novo em instantes.');
        }

        $this->audit($request, $user, $status, $error, (int) ((hrtime(true) - $started) / 1_000_000));

        return $response;
    }

    protected function audit(Request $request, mixed $user, string $status, ?string $error, int $durationMs): void
    {
        try {
            $http = request();
            McpAuditLog::query()->withoutGlobalScopes()->create([
                'clinic_id' => $user instanceof User ? $user->clinic_id : null,
                'user_id' => $user instanceof User ? $user->id : null,
                'oauth_client_id' => $http->attributes->get(EnsureMcpUser::CLIENT_ATTRIBUTE),
                'tool' => $this->name(),
                'arguments' => self::sanitizeArguments($request->all()),
                'status' => $status,
                'error' => $error !== null ? mb_substr($error, 0, 255) : null,
                'duration_ms' => $durationMs,
                'ip' => $http->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Só valores simples e curtos vão para o registro.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function sanitizeArguments(array $arguments): array
    {
        $clean = [];
        foreach (array_slice($arguments, 0, 20, true) as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }
            $key = mb_substr((string) $key, 0, 40);
            if (is_int($value) || is_float($value)) {
                // Número também pode ser telefone/CPF (ex.: busca=11987651234 enviado como número).
                $text = (string) $value;
                if (in_array($key, self::NAME_SEARCH_ARGUMENTS, true)) {
                    $value = mb_substr($text, 0, 1).'***';
                } elseif (Pii::scrub($text) !== $text) {
                    $value = Pii::scrub($text);
                }
            } elseif (is_string($value)) {
                // Nomes buscados não vão inteiros para o registro (só a inicial).
                $value = in_array($key, self::NAME_SEARCH_ARGUMENTS, true) && $value !== ''
                    ? mb_substr($value, 0, 1).'***'
                    : Pii::scrub(mb_substr($value, 0, 100));
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    // ---- Períodos (calendário de São Paulo) -------------------------------------------------

    /** Mês "AAAA-MM" informado ou o mês atual de São Paulo. */
    protected function month(Request $request, string $key = 'mes'): string
    {
        $value = trim((string) $request->get($key, ''));
        if ($value === '') {
            return substr(ClinicMonth::of(), 0, 7);
        }
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            throw new McpToolException('Informe o mês no formato AAAA-MM (ex.: 2026-11).');
        }

        return $value;
    }

    /**
     * Período em datas de Brasília. Aceita "de"/"ate" (AAAA-MM-DD) ou "mes" (AAAA-MM).
     * Sem nada: o mês atual até hoje (ou o padrão informado).
     *
     * @return array{0: string, 1: string}
     */
    protected function period(Request $request, ?array $default = null): array
    {
        $from = trim((string) $request->get('de', ''));
        $to = trim((string) $request->get('ate', ''));

        if ($from === '' && $to === '') {
            if (trim((string) $request->get('mes', '')) !== '' || $default === null) {
                $month = $this->month($request);
                $start = ClinicMonth::parseMonth($month);
                $end = $start->endOfMonth();
                $today = ClinicMonth::todayCalendar();
                if ($start->lte($today) && $end->gte($today) && $default === null) {
                    $end = $today;
                }

                return [$start->toDateString(), $end->toDateString()];
            }

            return $default;
        }

        $from = $from !== '' ? $from : $to;
        $to = $to !== '' ? $to : $from;
        foreach ([$from, $to] as $date) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                throw new McpToolException('Informe as datas no formato AAAA-MM-DD (ex.: 2026-11-03).');
            }
        }
        if ($to < $from) {
            throw new McpToolException('A data final precisa ser igual ou depois da inicial.');
        }
        if (ClinicMonth::parseDate($from)->diffInDays(ClinicMonth::parseDate($to)) > self::MAX_RANGE_DAYS) {
            throw new McpToolException('Use um período de até 1 ano.');
        }

        return [$from, $to];
    }

    protected function requirePermission(User $user, string $permission, string $what): void
    {
        if (! $user->can($permission)) {
            throw new McpToolException("Sua conta não tem permissão para ver {$what} no HOF Pay.");
        }
    }

    protected function limit(Request $request, int $default, int $max): int
    {
        return min(max((int) $request->get('limite', $default), 1), $max);
    }
}
