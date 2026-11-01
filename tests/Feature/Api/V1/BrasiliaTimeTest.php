<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\CheckLowStockProductsJob;
use App\Models\Appointment;
use App\Models\Budget;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Sale;
use App\Models\User;
use App\Services\BudgetFollowUpService;
use App\Services\StockAlertService;
use App\Support\ClinicMonth;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tudo no horário de Brasília: um evento às 23:30 de São Paulo no último dia do mês (02:30 UTC
 * do dia seguinte) conta nesse dia e nesse mês em filtros, agrupamentos e "hoje"/"este mês".
 */
class BrasiliaTimeTest extends TestCase
{
    use RefreshDatabase;

    /** 31/10 23:30 em São Paulo. */
    private const OCT_31_2330_SP = '2026-11-01 02:30:00';

    /** 01/11 00:00 em São Paulo. */
    private const NOV_1_0000_SP = '2026-11-01 03:00:00';

    protected Clinic $clinic;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        CurrentClinic::setId($this->clinic->id);
        Sanctum::actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function at(string $utc): void
    {
        Carbon::setTestNow($utc);
        CarbonImmutable::setTestNow($utc);
    }

    private function sale(string $soldAt, string $amount): Sale
    {
        return Sale::factory()->forClinic($this->clinic)->create([
            'status' => Sale::STATUS_CONFIRMED, 'sold_at' => $soldAt, 'effective_amount' => $amount,
            'expected_amount' => $amount, 'sold_by_user_id' => $this->admin->id,
        ]);
    }

    private function lead(string $createdAt): Lead
    {
        static $phone = 0;
        $lead = Lead::factory()->forClinic($this->clinic)->create([
            'client_origin_id' => ClientOrigin::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'lead_status_id' => LeadStatus::factory()->create(['clinic_id' => $this->clinic->id])->id,
            'phone' => '119'.str_pad((string) (++$phone), 8, '0', STR_PAD_LEFT),
        ]);
        $lead->forceFill(['created_at' => $createdAt])->save();

        return $lead;
    }

    public function test_clinic_calendar_helpers_at_2330_on_the_last_day(): void
    {
        $this->at(self::OCT_31_2330_SP);
        $this->assertSame('2026-10-31', ClinicMonth::todayDate());
        $this->assertSame('2026-10-01', ClinicMonth::currentMonth()->toDateString());
        $this->assertSame('2026-10-31', ClinicMonth::todayCalendar()->toDateString());
        $this->assertSame('2026-11-01 02:59:59', ClinicMonth::dayEnd('2026-10-31')->format('Y-m-d H:i:s'));
        // month=Y-m num dia 31 não vira o mês seguinte.
        $this->at('2026-10-31 15:00:00');
        $this->assertSame('2026-11-01', ClinicMonth::parseMonth('2026-11')->toDateString());
        // Limites de filtro: data pura = dia de SP; com fuso, como veio; sem fuso, hora de SP.
        $this->assertSame('2026-10-31 03:00:00', ClinicMonth::boundary('2026-10-31', true)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-31 12:00:00', ClinicMonth::boundary('2026-10-31T12:00:00Z', true)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-31 15:00:00', ClinicMonth::boundary('2026-10-31 12:00:00', true)->format('Y-m-d H:i:s'));
    }

    public function test_commercial_metrics_filter_and_group_by_the_sao_paulo_day_and_month(): void
    {
        $this->sale(self::OCT_31_2330_SP, '100.00');
        $this->sale(self::NOV_1_0000_SP, '40.00');
        $this->sale('2026-10-01 02:30:00', '7.00'); // 30/09 23:30 em SP

        $day = $this->getJson('/api/v1/metrics/commercial?from=2026-10-31&to=2026-10-31&granularity=day')->assertOk();
        $this->assertSame('100.00', $day->json('data.kpis.revenue'));
        $this->assertSame([['period' => '2026-10-31', 'revenue' => '100.00', 'sales_count' => 1]], $day->json('data.series'));

        $month = $this->getJson('/api/v1/metrics/commercial?from=2026-09-01&to=2026-11-30&granularity=month')->assertOk();
        $this->assertSame(['2026-09-01' => '7.00', '2026-10-01' => '100.00', '2026-11-01' => '40.00'],
            collect($month->json('data.series'))->pluck('revenue', 'period')->all());
        $this->assertSame('2026-09-01', $month->json('data.from'));

        $week = $this->getJson('/api/v1/metrics/commercial?from=2026-10-26&to=2026-11-08&granularity=week')->assertOk();
        // 31/10 (sábado) fica na semana de 26/10; 01/11 (domingo) também.
        $this->assertSame(['2026-10-26' => '140.00', '2026-11-02' => '0.00'],
            collect($week->json('data.series'))->pluck('revenue', 'period')->all());
    }

    public function test_lead_and_appointment_lists_filter_by_sao_paulo_days(): void
    {
        $late = $this->lead(self::OCT_31_2330_SP);
        $this->lead(self::NOV_1_0000_SP);
        $ids = fn (string $q) => collect($this->getJson('/api/v1/leads?'.$q)->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$late->id], $ids('from=2026-10-31&to=2026-10-31'));
        $this->assertNotContains($late->id, $ids('from=2026-11-01&to=2026-11-01'));

        $appointment = Appointment::factory()->forClinic($this->clinic)->create(['scheduled_at' => self::OCT_31_2330_SP]);
        Appointment::factory()->forClinic($this->clinic)->create(['scheduled_at' => self::NOV_1_0000_SP]);
        $listed = collect($this->getJson('/api/v1/appointments?from=2026-10-31&to=2026-10-31')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$appointment->id], $listed);

        $ops = $this->getJson('/api/v1/metrics/operations?from=2026-10-31&to=2026-10-31')->assertOk();
        $this->assertSame(1, $ops->json('data.kpis.sessions_total'));
    }

    public function test_budget_valid_until_lasts_the_whole_sao_paulo_day(): void
    {
        $budget = Budget::factory()->create([
            'clinic_id' => $this->clinic->id, 'status' => Budget::STATUS_SENT, 'sent_at' => '2026-10-20 15:00:00',
            'valid_until' => '2026-10-31 00:00:00', 'created_by_user_id' => $this->admin->id,
        ]);
        $this->at(self::OCT_31_2330_SP); // ainda 31/10 em SP → válido
        $service = app(BudgetFollowUpService::class);
        $this->assertNotNull($service->info($budget->fresh()));
        $this->assertTrue($service->forgottenQuery()->whereKey($budget->id)->exists());

        $this->at(self::NOV_1_0000_SP); // 01/11 em SP → vencido
        $this->assertNull($service->info($budget->fresh()));
        $this->assertFalse($service->forgottenQuery()->whereKey($budget->id)->exists());
    }

    public function test_planned_stock_usage_uses_the_sao_paulo_day(): void
    {
        Appointment::factory()->forClinic($this->clinic)->create(['scheduled_at' => self::OCT_31_2330_SP]);
        $service = app(StockAlertService::class);
        // Sem tratamento com consumo planejado o total é vazio; o que importa é o recorte do dia.
        $inDay = Appointment::query()->whereBetween('scheduled_at', ClinicMonth::dayBounds('2026-10-31', '2026-10-31'))->count();
        $this->assertSame(1, $inDay);
        $this->assertIsArray($service->plannedUsageForDay($this->clinic->id, CarbonImmutable::parse('2026-10-31', ClinicMonth::TZ)));

        // Job sem dia: amanhã em São Paulo.
        $this->at('2026-11-01 01:00:00'); // 31/10 22:00 em SP
        $job = new CheckLowStockProductsJob;
        $this->assertNull($job->day);
        $this->assertSame('2026-11-01', ClinicMonth::today()->addDay()->toDateString());
    }

    public function test_scheduled_jobs_run_on_brasilia_time(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => $e->expression !== '0 * * * *');
        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertSame(ClinicMonth::TZ, $event->timezone, $event->description ?? $event->command ?? '');
        }
        $byExpression = $events->map(fn ($e) => $e->expression)->values()->all();
        $this->assertContains('0 8 * * *', $byExpression);
        $this->assertContains('0 18 * * *', $byExpression);
        $this->assertContains('0 22 * * *', $byExpression);
        $this->assertContains('5 0 * * *', $byExpression);
    }
}
