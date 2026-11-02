<?php

namespace Tests\Feature\Mcp;

use App\Models\Appointment;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\PatientReturn;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\ClinicMonth;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultClientOrigins;
use App\Support\EnsureDefaultLeadStatuses;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

/** Duas clínicas com dados (A e B), um admin em cada e contas com menos permissão em A. */
trait McpFixtures
{
    protected Clinic $clinicA;

    protected Clinic $clinicB;

    protected User $adminA;

    protected User $adminB;

    /** Tem integrations.manage, mas não finance.view. */
    protected User $managerNoFinance;

    /** Recepção: sem integrations.manage. */
    protected User $receptionist;

    protected Client $patientA;

    protected Client $patientB;

    protected Lead $leadA;

    /** Telefones completos de A e B (não podem aparecer em nenhuma resposta). */
    protected array $phones = [];

    /** @var array{0: string, 1: string}|null */
    private static ?array $oauthKeys = null;

    /** Chaves RSA só do teste (o deploy usa `php artisan passport:keys`). */
    protected function useTestOAuthKeys(): void
    {
        if (self::$oauthKeys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            self::$oauthKeys = [$private, openssl_pkey_get_details($key)['key']];
        }
        config(['passport.private_key' => self::$oauthKeys[0], 'passport.public_key' => self::$oauthKeys[1]]);
    }

    protected function seedMcpFixtures(): void
    {
        $this->useTestOAuthKeys();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->clinicA = Clinic::factory()->create(['name' => 'Clínica Alfa']);
        $this->clinicB = Clinic::factory()->create(['name' => 'Clínica Beta']);

        $this->adminA = User::factory()->forClinic($this->clinicA)->create(['name' => 'Ana Admin', 'email' => 'ana@alfa.test', 'password' => 'Senha-Forte-123!']);
        $this->adminA->assignRole('admin');
        $this->adminB = User::factory()->forClinic($this->clinicB)->create(['name' => 'Bia Admin', 'email' => 'bia@beta.test']);
        $this->adminB->assignRole('admin');

        $role = Role::findOrCreate('gestor-sem-financeiro', 'web');
        $role->syncPermissions(['integrations.manage', 'metrics.view', 'leads.view', 'clients.view', 'appointments.view', 'budgets.view', 'users.view']);
        $this->managerNoFinance = User::factory()->forClinic($this->clinicA)->create(['name' => 'Gil Gestor']);
        $this->managerNoFinance->assignRole($role);

        $this->receptionist = User::factory()->forClinic($this->clinicA)->create(['name' => 'Rita Recepção']);
        $this->receptionist->assignRole('receptionist');

        [$this->patientA, $this->leadA] = $this->seedClinicData($this->clinicA, $this->adminA, 'Paula Alfa', '11987650001', 'Lia Lead Alfa', '11976540001', '1500.00');
        [$this->patientB] = $this->seedClinicData($this->clinicB, $this->adminB, 'Paula Beta', '21987650002', 'Lia Lead Beta', '21976540002', '9999.00');
        CurrentClinic::forget();
    }

    /** @return array{0: Client, 1: Lead} */
    private function seedClinicData(Clinic $clinic, User $admin, string $patient, string $whatsapp, string $leadName, string $leadPhone, string $amount): array
    {
        CurrentClinic::setId($clinic->id);
        EnsureDefaultClientOrigins::run($clinic);
        EnsureDefaultLeadStatuses::run($clinic);
        $this->phones[] = $whatsapp;
        $this->phones[] = $leadPhone;

        $client = Client::factory()->forClinic($clinic)->create([
            'name' => $patient, 'whatsapp' => $whatsapp, 'notes' => 'Alergia a lidocaína', 'main_pains' => 'Rugas na testa',
        ]);
        $lead = Lead::factory()->forClinic($clinic)->create(['name' => $leadName, 'phone' => $leadPhone, 'created_by_user_id' => $admin->id, 'notes' => 'Prefere manhã']);

        $sale = Sale::factory()->forClinic($clinic)->create([
            'client_id' => $client->id, 'sold_by_user_id' => $admin->id, 'status' => Sale::STATUS_CONFIRMED,
            'sold_at' => ClinicMonth::now()->startOfDay()->addHours(10)->utc(),
            'expected_amount' => $amount, 'effective_amount' => $amount,
        ]);
        Budget::factory()->forClinic($clinic)->create([
            'sale_id' => $sale->id, 'client_id' => $client->id, 'created_by_user_id' => $admin->id,
            'status' => Budget::STATUS_SENT, 'sent_at' => now()->subDays(3), 'expected_amount' => '800.00', 'effective_amount' => '800.00',
        ]);
        Appointment::factory()->forClinic($clinic)->create([
            'client_id' => $client->id, 'treatment_id' => null, 'professional_user_id' => $admin->id,
            'scheduled_at' => ClinicMonth::now()->startOfDay()->addHours(15)->utc(),
        ]);
        PatientReturn::query()->create([
            'clinic_id' => $clinic->id, 'client_id' => $client->id, 'label' => 'Toxina botulínica',
            'due_on' => ClinicMonth::todayDate(), 'status' => PatientReturn::STATUS_PENDING,
        ]);
        Product::factory()->forClinic($clinic)->create(['name' => 'Toxina '.$clinic->name, 'stock_quantity' => '1.0000', 'min_stock' => '5.0000']);

        return [$client, $lead];
    }
}
