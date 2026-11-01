<?php

namespace Tests\Feature\Api\V1;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\User;
use App\Notifications\AppointmentConfirmationDigestNotification;
use App\Services\AppointmentConfirmationService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create(['name' => 'Clínica Bella']);
        $this->admin = User::factory()->forClinic($this->clinic)->create(['name' => 'Ana Admin']);
        $this->admin->assignRole('admin');
        $this->reception = User::factory()->forClinic($this->clinic)->create(['name' => 'Rita Recepção']);
        $this->reception->assignRole('receptionist');
        // 2026-10-20 15:00 UTC = 12:00 in Brasília → "amanhã" = 2026-10-21.
        Carbon::setTestNow('2026-10-20 15:00:00');
        CarbonImmutable::setTestNow('2026-10-20 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function appointment(string $scheduledAtUtc, array $attrs = [], ?Clinic $clinic = null): Appointment
    {
        $clinic ??= $this->clinic;
        $client = Client::factory()->forClinic($clinic)->create(['name' => 'Paciente '.$scheduledAtUtc]);

        return Appointment::factory()->forClinic($clinic)->create([
            'client_id' => $client->id,
            'scheduled_at' => $scheduledAtUtc,
        ] + $attrs);
    }

    public function test_tomorrow_list_uses_the_clinic_day_and_only_scheduled_sessions(): void
    {
        $early = $this->appointment('2026-10-21 03:30:00'); // 00:30 Brasília on the 21st
        $late = $this->appointment('2026-10-22 02:30:00');  // 23:30 Brasília on the 21st
        $this->appointment('2026-10-21 02:30:00');          // 23:30 Brasília on the 20th: today
        $this->appointment('2026-10-21 13:00:00', ['status' => Appointment::STATUS_CANCELLED]);
        $this->appointment('2026-10-21 13:00:00', [], Clinic::factory()->create()); // other clinic

        Sanctum::actingAs($this->reception);
        $response = $this->getJson('/api/v1/appointments/confirmations')->assertOk()
            ->assertJsonPath('meta.date', '2026-10-21')
            ->assertJsonPath('meta.counts.total', 2)
            ->assertJsonPath('meta.counts.pending', 2)
            ->assertJsonPath('data.0.confirmation_status', 'pending');
        $this->assertSame([$early->id, $late->id], collect($response->json('data'))->pluck('id')->all());

        $this->getJson('/api/v1/appointments/confirmations?date=2026-10-20')->assertOk()->assertJsonPath('meta.counts.total', 1);
    }

    public function test_confirmation_states_can_be_recorded_and_are_validated(): void
    {
        $appointment = $this->appointment('2026-10-21 13:00:00');
        Sanctum::actingAs($this->reception);

        foreach (['confirmed', 'not_confirmed', 'declined', 'pending'] as $status) {
            $this->patchJson("/api/v1/appointments/{$appointment->id}/confirmation-status", ['confirmation_status' => $status])
                ->assertOk()
                ->assertJsonPath('data.confirmation_status', $status)
                ->assertJsonPath('data.confirmation_status_by.name', 'Rita Recepção');
        }
        $this->patchJson("/api/v1/appointments/{$appointment->id}/confirmation-status", ['confirmation_status' => 'talvez'])
            ->assertUnprocessable()->assertJsonValidationErrors(['confirmation_status']);

        $cancelled = $this->appointment('2026-10-21 14:00:00', ['status' => Appointment::STATUS_CANCELLED]);
        $this->patchJson("/api/v1/appointments/{$cancelled->id}/confirmation-status", ['confirmation_status' => 'confirmed'])
            ->assertUnprocessable();

        // Other clinic's appointment is not reachable.
        $foreign = $this->appointment('2026-10-21 13:00:00', [], Clinic::factory()->create());
        $this->patchJson("/api/v1/appointments/{$foreign->id}/confirmation-status", ['confirmation_status' => 'confirmed'])
            ->assertNotFound();

        // Stock users have no agenda access.
        $stock = User::factory()->forClinic($this->clinic)->create();
        $stock->assignRole('stock');
        Sanctum::actingAs($stock);
        $this->getJson('/api/v1/appointments/confirmations')->assertForbidden();
        $this->patchJson("/api/v1/appointments/{$appointment->id}/confirmation-status", ['confirmation_status' => 'confirmed'])
            ->assertForbidden();
    }

    public function test_daily_digest_notifies_agenda_users_once_per_day(): void
    {
        Notification::fake();
        $this->appointment('2026-10-21 13:00:00');
        $this->appointment('2026-10-21 14:00:00', ['confirmation_status' => Appointment::CONFIRMATION_CONFIRMED]);
        $stock = User::factory()->forClinic($this->clinic)->create();
        $stock->assignRole('stock');

        $service = app(AppointmentConfirmationService::class);
        $this->assertSame(2, $service->digestClinic($this->clinic));

        Notification::assertSentTo($this->reception, AppointmentConfirmationDigestNotification::class, function ($n) {
            return $n->total === 2 && $n->toConfirm === 1 && $n->date === '2026-10-21';
        });
        Notification::assertNotSentTo($stock, AppointmentConfirmationDigestNotification::class);

        // Nothing to confirm → no digest.
        Appointment::query()->withoutGlobalScopes()->update(['confirmation_status' => Appointment::CONFIRMATION_CONFIRMED]);
        $this->assertSame(0, $service->digestClinic($this->clinic));
    }

    public function test_metrics_report_confirmation_and_no_show_rates(): void
    {
        // Past sessions (before "now"): completed+confirmed, no-show confirmed, no-show pending, declined.
        $this->appointment('2026-10-10 13:00:00', ['status' => Appointment::STATUS_COMPLETED, 'confirmation_status' => 'confirmed']);
        $this->appointment('2026-10-11 13:00:00', ['confirmation_status' => 'confirmed']);
        $this->appointment('2026-10-12 13:00:00');
        $this->appointment('2026-10-13 13:00:00', ['status' => Appointment::STATUS_CANCELLED, 'confirmation_status' => 'declined']);
        // Cancelled by the clinic: outside the base.
        $this->appointment('2026-10-14 13:00:00', ['status' => Appointment::STATUS_CANCELLED]);
        // Future: confirmed, not no-show.
        $this->appointment('2026-10-25 13:00:00', ['confirmation_status' => 'not_confirmed']);

        Sanctum::actingAs($this->admin);
        $c = $this->getJson('/api/v1/metrics/operations?from=2026-10-01&to=2026-10-31')->assertOk()->json('data.confirmation');

        $this->assertSame(5, $c['total']);
        $this->assertSame(2, $c['confirmed']);
        $this->assertSame(1, $c['declined']);
        $this->assertSame(1, $c['not_confirmed']);
        $this->assertSame('40.00', $c['confirmation_rate']);
        $this->assertSame(3, $c['past_sessions']);
        $this->assertSame(2, $c['no_show']);
        $this->assertSame('66.67', $c['no_show_rate']);
        $this->assertSame('50.00', $c['no_show_rate_confirmed']);
    }
}
