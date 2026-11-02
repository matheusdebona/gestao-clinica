<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use App\Services\ExpenseGenerator;
use App\Services\RegisterClinicAdminService;
use App\Support\CurrentClinic;
use App\Support\Demo\DemoClinicGuard;
use App\Support\Demo\DemoManifest;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DemoSeedClinicCommandTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $target;

    private Clinic $other;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->dir = sys_get_temp_dir().'/demo-seed-test-'.uniqid();
        DemoManifest::$directory = $this->dir;

        $register = app(RegisterClinicAdminService::class);
        $this->other = $register->register([
            'clinic_name' => 'Clínica Real', 'name' => 'Dona Real', 'email' => 'real@example.com', 'password' => 'secret-123456',
        ])->clinic;
        // A outra clínica tem dados próprios que não podem mudar.
        CurrentClinic::setId($this->other->id);
        DB::table('clients')->insert([
            'clinic_id' => $this->other->id, 'name' => 'Paciente Real', 'whatsapp' => '11900000001',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        CurrentClinic::forget();
        $this->target = $register->register([
            'clinic_name' => 'Clínica Teste', 'name' => 'Admin Teste', 'email' => 'teste@example.com', 'password' => 'secret-123456',
        ])->clinic;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        DemoManifest::$directory = null;
        Carbon::setTestNow();
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function seedArgs(array $extra = []): array
    {
        return $extra + [
            'clinic' => $this->target->id,
            '--confirm-name' => 'Clínica Teste',
            '--from' => '2026-09',
            '--to' => '2026-10',
            '--today' => '2026-10-10 20:00',
            '--scale' => '0.2',
            '--seed' => '6',
        ];
    }

    public function test_refuses_unknown_clinic_and_wrong_name(): void
    {
        $before = DemoClinicGuard::countsByClinic();

        $this->artisan('demo:seed-clinic', ['clinic' => 999, '--confirm-name' => 'Clínica Teste'])->assertFailed();
        $this->artisan('demo:seed-clinic', $this->seedArgs(['--confirm-name' => 'Clínica Real']))->assertFailed();
        // O nome de OUTRA clínica não serve como confirmação para o id alvo.
        $this->artisan('demo:seed-clinic', ['clinic' => $this->other->id, '--confirm-name' => 'Clínica Teste'])->assertFailed();
        $this->artisan('demo:purge-clinic', ['clinic' => $this->target->id, '--confirm-name' => 'Clínica Teste'])->assertFailed();

        $this->assertSame($before, DemoClinicGuard::countsByClinic());
        $this->assertFileDoesNotExist(DemoManifest::path($this->target->id));
    }

    public function test_seed_writes_only_to_target_and_purge_restores_it(): void
    {
        $otherBefore = DemoClinicGuard::fingerprintOthers($this->target->id);
        $countsBefore = DemoClinicGuard::countsByClinic();

        $this->artisan('demo:seed-clinic', $this->seedArgs())->assertSuccessful();

        // Nada de outra clínica mudou (contagem + updated_at de cada tabela).
        $this->assertSame($otherBefore, DemoClinicGuard::fingerprintOthers($this->target->id));
        $this->assertSame($countsBefore[$this->other->id] ?? [], DemoClinicGuard::countsByClinic()[$this->other->id] ?? []);

        // Tudo que o seed criou e tem clinic_id é da clínica alvo.
        $manifest = DemoManifest::load($this->target->id);
        $this->assertNotEmpty($manifest['created']['leads'] ?? []);
        $this->assertNotEmpty($manifest['created']['clients'] ?? []);
        $this->assertNotEmpty($manifest['created']['appointments'] ?? []);
        foreach ($manifest['created'] as $table => $ids) {
            if (Schema::hasColumn($table, 'clinic_id')) {
                $this->assertSame(0, DB::table($table)->whereIn('id', $ids)->where('clinic_id', '!=', $this->target->id)->count(), $table);
            }
        }
        $this->assertGreaterThan(0, DB::table('sales')->where('clinic_id', $this->target->id)->where('status', 'confirmed')->count());

        // Usuários de demonstração: e-mail reservado, ativos, sem nenhum e-mail/notificação disparado.
        $demoUsers = User::query()->whereIn('id', $manifest['created']['users'])->get();
        $this->assertCount(4, $demoUsers);
        $this->assertTrue($demoUsers->every(fn (User $u) => str_ends_with($u->email, '@example.com') && (int) $u->clinic_id === $this->target->id));
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('jobs')->count());

        // O cron de despesas gera o mês seguinte a partir das recorrentes da demo: o purge também leva essas.
        CurrentClinic::setId($this->target->id);
        $this->assertGreaterThan(0, app(ExpenseGenerator::class)->generateMonth($this->target->id, CarbonImmutable::parse('2026-12-01')));
        CurrentClinic::forget();

        // Não roda duas vezes por cima.
        $this->artisan('demo:seed-clinic', $this->seedArgs())->assertFailed();

        $this->artisan('demo:purge-clinic', ['clinic' => $this->target->id, '--confirm-name' => 'Clínica Teste'])->assertSuccessful();

        $this->assertSame($countsBefore, DemoClinicGuard::countsByClinic());
        $this->assertSame($otherBefore, DemoClinicGuard::fingerprintOthers($this->target->id));
        $this->assertFileDoesNotExist(DemoManifest::path($this->target->id));
    }
}
