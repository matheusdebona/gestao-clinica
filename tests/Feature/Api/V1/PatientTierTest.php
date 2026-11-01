<?php

namespace Tests\Feature\Api\V1;

use App\Models\Client;
use App\Models\Clinic;
use App\Models\PatientTier;
use App\Models\Sale;
use App\Models\User;
use App\Services\RegisterClinicAdminService;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultPatientTiers;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientTierTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        CarbonImmutable::setTestNow('2026-11-01 12:00:00');

        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        $this->reception = User::factory()->forClinic($this->clinic)->create();
        $this->reception->assignRole('receptionist');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function seedDefaults(): void
    {
        EnsureDefaultPatientTiers::run($this->clinic);
    }

    private function patient(string $name, array $sales = []): Client
    {
        $client = Client::factory()->forClinic($this->clinic)->create(['name' => $name]);
        foreach ($sales as $sale) {
            Sale::factory()->forClinic($this->clinic)->create([
                'client_id' => $client->id,
                'sold_by_user_id' => $this->admin->id,
                'status' => $sale['status'] ?? Sale::STATUS_CONFIRMED,
                'effective_amount' => $sale['amount'],
                'sold_at' => $sale['sold_at'] ?? CarbonImmutable::now()->subDays(10),
            ]);
        }

        return $client;
    }

    /** @return array<string, string|null> name => tier name */
    private function listTiers(string $query = ''): array
    {
        $rows = $this->getJson('/api/v1/clients'.$query)->assertOk()->json('data');

        return collect($rows)->mapWithKeys(fn ($row) => [$row['name'] => $row['tier']['name'] ?? null])->all();
    }

    // ---- defaults / seed -------------------------------------------------

    public function test_default_tiers_are_bronze_prata_ouro_diamante(): void
    {
        $this->seedDefaults();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/patient-tiers')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name', 'Bronze')
            ->assertJsonPath('data.0.color', 'bronze')
            ->assertJsonPath('data.0.range_label', 'R$ 0,00 a R$ 5.000,00')
            ->assertJsonPath('data.1.name', 'Prata')
            ->assertJsonPath('data.1.range_label', 'R$ 5.000,01 a R$ 10.000,00')
            ->assertJsonPath('data.2.name', 'Ouro')
            ->assertJsonPath('data.2.range_label', 'R$ 10.000,01 a R$ 20.000,00')
            ->assertJsonPath('data.3.name', 'Diamante')
            ->assertJsonPath('data.3.color', 'diamond')
            ->assertJsonPath('data.3.max_amount', null)
            ->assertJsonPath('data.3.range_label', 'Acima de R$ 20.000,00');
    }

    public function test_seed_runs_for_existing_clinics_and_is_idempotent(): void
    {
        $other = Clinic::factory()->create();
        $custom = Clinic::factory()->create();
        DB::table('patient_tiers')->insert([
            'clinic_id' => $custom->id, 'name' => 'VIP', 'color' => 'purple', 'min_amount' => '1000.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        EnsureDefaultPatientTiers::runAll();
        EnsureDefaultPatientTiers::runAll();

        $this->assertSame(4, DB::table('patient_tiers')->where('clinic_id', $this->clinic->id)->count());
        $this->assertSame(4, DB::table('patient_tiers')->where('clinic_id', $other->id)->count());
        // Clínica que já tinha faixas fica como está.
        $this->assertSame(['VIP'], DB::table('patient_tiers')->where('clinic_id', $custom->id)->pluck('name')->all());
    }

    public function test_the_migration_seeds_existing_clinics(): void
    {
        $migration = require database_path('migrations/2026_11_01_110000_create_patient_tiers.php');
        $migration->down();
        $migration->up();

        $this->assertSame(
            ['Bronze', 'Prata', 'Ouro', 'Diamante'],
            DB::table('patient_tiers')->where('clinic_id', $this->clinic->id)->orderBy('min_amount')->pluck('name')->all(),
        );
    }

    public function test_new_clinic_gets_default_tiers(): void
    {
        $user = app(RegisterClinicAdminService::class)->register([
            'clinic_name' => 'Clínica Nova',
            'name' => 'Dona',
            'email' => 'dona@example.com',
            'password' => 'senha-segura-123',
        ]);

        $this->assertSame(
            ['Bronze', 'Prata', 'Ouro', 'Diamante'],
            DB::table('patient_tiers')->where('clinic_id', $user->clinic_id)->orderBy('min_amount')->pluck('name')->all(),
        );
    }

    // ---- CRUD / permissions -----------------------------------------------

    public function test_admin_can_create_update_and_delete_tiers(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/patient-tiers', ['name' => ' Bronze ', 'color' => 'bronze', 'min_amount' => 0])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Bronze')
            ->assertJsonPath('data.range_label', 'Qualquer valor')
            ->json('data.id');

        $this->postJson('/api/v1/patient-tiers', ['name' => 'Prata', 'color' => 'silver', 'min_amount' => '5000.01'])
            ->assertCreated()
            ->assertJsonPath('tiers.0.range_label', 'R$ 0,00 a R$ 5.000,00')
            ->assertJsonPath('tiers.1.range_label', 'Acima de R$ 5.000,00');

        $this->putJson("/api/v1/patient-tiers/{$id}", ['name' => 'Bronze', 'color' => null, 'min_amount' => '100'])
            ->assertOk()
            ->assertJsonPath('data.color', null)
            ->assertJsonPath('data.range_label', 'R$ 100,00 a R$ 5.000,00');

        $this->deleteJson("/api/v1/patient-tiers/{$id}")->assertOk();
        $this->assertSame(1, PatientTier::query()->withoutGlobalScopes()->count());
    }

    public function test_validation_rejects_duplicate_min_negative_and_unknown_color(): void
    {
        $this->seedDefaults();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/patient-tiers', ['name' => 'Outra', 'min_amount' => '5000.01'])
            ->assertUnprocessable()->assertJsonValidationErrors('min_amount');
        $this->postJson('/api/v1/patient-tiers', ['name' => 'Negativa', 'min_amount' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors('min_amount');
        $this->postJson('/api/v1/patient-tiers', ['name' => 'Cor', 'min_amount' => 50000, 'color' => 'pink'])
            ->assertUnprocessable()->assertJsonValidationErrors('color');
        $this->postJson('/api/v1/patient-tiers', ['name' => '   ', 'min_amount' => 50000])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/patient-tiers', ['name' => 'Centavos', 'min_amount' => '1.234'])
            ->assertUnprocessable()->assertJsonValidationErrors('min_amount');
    }

    public function test_reception_can_read_tiers_but_not_manage_them(): void
    {
        $this->seedDefaults();
        Sanctum::actingAs($this->reception);
        $tier = PatientTier::query()->withoutGlobalScopes()->where('clinic_id', $this->clinic->id)->first();

        $this->getJson('/api/v1/patient-tiers')->assertOk()->assertJsonCount(4, 'data');
        $this->postJson('/api/v1/patient-tiers', ['name' => 'X', 'min_amount' => 1])->assertForbidden();
        $this->putJson("/api/v1/patient-tiers/{$tier->id}", ['name' => 'X', 'min_amount' => 1])->assertForbidden();
        $this->deleteJson("/api/v1/patient-tiers/{$tier->id}")->assertForbidden();
        $this->postJson('/api/v1/patient-tiers/restore-defaults')->assertForbidden();
    }

    public function test_tiers_of_another_clinic_are_not_reachable(): void
    {
        $other = Clinic::factory()->create();
        EnsureDefaultPatientTiers::run($other);
        $foreign = PatientTier::query()->withoutGlobalScopes()->where('clinic_id', $other->id)->first();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/patient-tiers')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/patient-tiers/{$foreign->id}", ['name' => 'X', 'min_amount' => 1])->assertNotFound();
        $this->deleteJson("/api/v1/patient-tiers/{$foreign->id}")->assertNotFound();
    }

    public function test_restore_defaults_only_when_clinic_has_no_tiers(): void
    {
        $this->seedDefaults();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/patient-tiers/restore-defaults')->assertUnprocessable();

        PatientTier::query()->withoutGlobalScopes()->where('clinic_id', $this->clinic->id)->delete();
        $this->postJson('/api/v1/patient-tiers/restore-defaults')->assertCreated()->assertJsonCount(4, 'data');
    }

    // ---- computation --------------------------------------------------------

    public function test_tier_boundaries(): void
    {
        $this->seedDefaults();
        $cases = [
            'Zero' => [null, 'Bronze'],
            'A 5000.00' => ['5000.00', 'Bronze'],
            'B 5000.01' => ['5000.01', 'Prata'],
            'C 10000.00' => ['10000.00', 'Prata'],
            'D 10000.01' => ['10000.01', 'Ouro'],
            'E 20000.00' => ['20000.00', 'Ouro'],
            'F 20000.01' => ['20000.01', 'Diamante'],
        ];
        foreach ($cases as $name => [$amount, $_]) {
            $this->patient($name, $amount === null ? [] : [['amount' => $amount]]);
        }
        Sanctum::actingAs($this->admin);

        $tiers = $this->listTiers();
        foreach ($cases as $name => [$_, $expected]) {
            $this->assertSame($expected, $tiers[$name], $name);
        }
    }

    public function test_sum_of_several_sales_crosses_the_boundary(): void
    {
        $this->seedDefaults();
        $this->patient('Soma', [['amount' => '2500.00'], ['amount' => '2500.01']]);
        Sanctum::actingAs($this->admin);

        $this->assertSame('Prata', $this->listTiers()['Soma']);
    }

    public function test_only_confirmed_sales_in_the_last_12_months_count(): void
    {
        $this->seedDefaults();
        $now = CarbonImmutable::now();
        $client = $this->patient('Janela', [
            ['amount' => '6000.00', 'sold_at' => $now->subMonths(12)->addMinute()], // dentro
            ['amount' => '9000.00', 'sold_at' => $now->subMonths(12)->subMinute()], // fora (mais de 12 meses)
            ['amount' => '9000.00', 'status' => Sale::STATUS_CANCELLED],
            ['amount' => '9000.00', 'status' => Sale::STATUS_DRAFT],
        ]);
        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.tier.name', 'Prata')
            ->assertJsonPath('data.purchases_12m', '6000.00');

        // A janela é móvel: um mês depois a venda de 6.000 sai e o paciente volta para Bronze.
        CarbonImmutable::setTestNow($now->addMonth());
        $this->getJson("/api/v1/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.tier.name', 'Bronze')
            ->assertJsonPath('data.purchases_12m', '0.00');
    }

    public function test_patient_below_the_lowest_tier_has_no_tier(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/patient-tiers', ['name' => 'VIP', 'min_amount' => 1000])->assertCreated();
        $this->patient('Pouco', [['amount' => '999.99']]);
        $this->patient('Vip', [['amount' => '1000.00']]);

        $tiers = $this->listTiers();
        $this->assertNull($tiers['Pouco']);
        $this->assertSame('VIP', $tiers['Vip']);
    }

    public function test_purchases_amount_is_hidden_without_sales_permission(): void
    {
        $this->seedDefaults();
        $client = $this->patient('Valor', [['amount' => '100.00']]);
        $viewer = User::factory()->forClinic($this->clinic)->create();
        $viewer->givePermissionTo('clients.view');
        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.tier.name', 'Bronze')
            ->assertJsonMissingPath('data.purchases_12m');
    }

    // ---- filter ---------------------------------------------------------------

    public function test_filter_by_tiers_and_none(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/patient-tiers', ['name' => 'Prata', 'min_amount' => '5000.01'])->assertCreated();
        $this->postJson('/api/v1/patient-tiers', ['name' => 'Ouro', 'min_amount' => '10000.01'])->assertCreated();
        $prata = PatientTier::query()->where('name', 'Prata')->value('id');
        $ouro = PatientTier::query()->where('name', 'Ouro')->value('id');

        $this->patient('Sem', [['amount' => '5000.00']]);
        $this->patient('P', [['amount' => '5000.01']]);
        $this->patient('O', [['amount' => '15000.00']]);

        $this->assertSame(['O', 'P', 'Sem'], array_keys($this->listTiers()));
        $this->assertSame(['P'], array_keys($this->listTiers("?tiers[]={$prata}")));
        $this->assertSame(['O', 'P'], array_keys($this->listTiers("?tiers={$prata},{$ouro}")));
        $this->assertSame(['Sem'], array_keys($this->listTiers('?tiers[]=none')));
        $this->assertSame(['O', 'Sem'], array_keys($this->listTiers("?tiers[]=none&tiers[]={$ouro}")));
        // Faixa inexistente (de outra clínica ou apagada) não traz ninguém e não quebra.
        $this->assertSame([], array_keys($this->listTiers('?tiers[]=999999')));
        // Valor inválido é ignorado = Todos.
        $this->assertSame(['O', 'P', 'Sem'], array_keys($this->listTiers('?tiers[]=abc')));
    }

    public function test_filter_combines_with_active_filter(): void
    {
        $this->seedDefaults();
        $this->patient('Ana Ouro', [['amount' => '12000.00']]);
        $this->patient('Ana Bronze', [['amount' => '10.00']]);
        Client::query()->withoutGlobalScopes()->create(['clinic_id' => $this->clinic->id, 'name' => 'Inativa Ouro', 'whatsapp' => '11900000000', 'is_active' => false]);
        $ouro = PatientTier::query()->withoutGlobalScopes()->where('clinic_id', $this->clinic->id)->where('name', 'Ouro')->value('id');
        Sanctum::actingAs($this->admin);

        $this->assertSame(['Ana Ouro'], array_keys($this->listTiers("?is_active=1&tiers[]={$ouro}")));
    }

    // ---- zero tiers -----------------------------------------------------------

    public function test_clinic_with_zero_tiers_works(): void
    {
        $this->seedDefaults();
        $client = $this->patient('Alguém', [['amount' => '30000.00']]);
        Sanctum::actingAs($this->admin);

        foreach ($this->getJson('/api/v1/patient-tiers')->json('data') as $tier) {
            $this->deleteJson("/api/v1/patient-tiers/{$tier['id']}")->assertOk();
        }

        $this->getJson('/api/v1/patient-tiers')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/clients')->assertOk()->assertJsonPath('data.0.tier', null);
        $this->getJson("/api/v1/clients/{$client->id}")->assertOk()->assertJsonPath('data.tier', null);
        // "Todos" (sem filtro) e "Sem faixa" trazem todo mundo; um id antigo não traz ninguém.
        $this->assertSame(['Alguém'], array_keys($this->listTiers()));
        $this->assertSame(['Alguém'], array_keys($this->listTiers('?tiers[]=none')));
        $this->assertSame([], array_keys($this->listTiers('?tiers[]=1')));
    }
}
