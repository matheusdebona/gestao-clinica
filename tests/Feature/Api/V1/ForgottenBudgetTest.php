<?php

namespace Tests\Feature\Api\V1;

use App\Models\Budget;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\User;
use App\Notifications\ForgottenBudgetsDigestNotification;
use App\Services\BudgetFollowUpService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ForgottenBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create(['name' => 'Ana Admin']);
        $this->admin->assignRole('admin');
        CurrentClinic::setId($this->clinic->id);
        // 15:00 UTC = 12:00 in São Paulo.
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

    private function budget(int $daysAgo, string $status = Budget::STATUS_SENT, array $extra = []): Budget
    {
        return Budget::factory()->forClinic($this->clinic)->create(array_merge([
            'status' => $status,
            'sent_at' => now()->subDays($daysAgo),
            'effective_amount' => '1500.00',
            'client_id' => Client::factory()->forClinic($this->clinic)->create(['name' => "Paciente {$daysAgo}d"])->id,
        ], $extra));
    }

    public function test_tiers_are_configurable_by_admin_only(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/clinic/settings')->assertOk()
            ->assertJsonPath('data.budget_follow_up_days', [2, 5, 10])
            ->assertJsonPath('data.whatsapp_placeholders.budget_follow_up.3', '{valor}');

        $this->putJson('/api/v1/clinic/settings', ['budget_follow_up_days' => [7, 3, 15]])->assertOk()
            ->assertJsonPath('data.budget_follow_up_days', [3, 7, 15]);
        $this->putJson('/api/v1/clinic/settings', ['budget_follow_up_days' => [3, 3]])->assertUnprocessable();
        $this->putJson('/api/v1/clinic/settings', ['budget_follow_up_days' => [0]])->assertUnprocessable();

        $seller = User::factory()->forClinic($this->clinic)->create();
        $seller->assignRole('seller');
        Sanctum::actingAs($seller);
        $this->putJson('/api/v1/clinic/settings', ['budget_follow_up_days' => [1]])->assertForbidden();
    }

    public function test_forgotten_filter_tiers_and_follow_up(): void
    {
        Sanctum::actingAs($this->admin);
        $one = $this->budget(1);          // below the first tier
        $three = $this->budget(3);        // tier 2
        $eleven = $this->budget(11);      // tier 10
        $this->budget(6, Budget::STATUS_ACCEPTED);
        $this->budget(6, Budget::STATUS_REJECTED);
        $this->budget(6, Budget::STATUS_EXPIRED);
        $this->budget(6, Budget::STATUS_SENT, ['valid_until' => now()->subDay()]); // validity over
        $this->budget(6, Budget::STATUS_DRAFT, ['sent_at' => null]);

        $response = $this->getJson('/api/v1/budgets?forgotten=1')->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing([$three->id, $eleven->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertSame(10, collect($response->json('data'))->firstWhere('id', $eleven->id)['forgotten']['tier']);
        $this->assertSame(11, collect($response->json('data'))->firstWhere('id', $eleven->id)['forgotten']['days']);

        $this->getJson("/api/v1/budgets/{$one->id}")->assertOk()->assertJsonPath('data.forgotten', null);

        // Following up removes it from the queue until the next tier.
        $this->postJson("/api/v1/budgets/{$three->id}/follow-up-whatsapp")->assertOk()
            ->assertJsonPath('data.follow_up_count', 1)
            ->assertJsonPath('data.forgotten.needs_follow_up', false);
        $this->getJson('/api/v1/leads/daily-analysis')->assertOk()
            ->assertJsonPath('data.follow_up.budgets.count', 1)
            ->assertJsonPath('data.follow_up.budgets.total', 2)
            ->assertJsonPath('data.follow_up.budgets.items.0.id', $eleven->id);

        // Two days later it reaches the 5-day tier and needs a new follow-up.
        Carbon::setTestNow('2026-10-22 15:00:00');
        CarbonImmutable::setTestNow('2026-10-22 15:00:00');
        $this->getJson("/api/v1/budgets/{$three->id}")->assertOk()
            ->assertJsonPath('data.forgotten.tier', 5)
            ->assertJsonPath('data.forgotten.needs_follow_up', true);

        // Only sent budgets can be followed up.
        $accepted = $this->budget(4, Budget::STATUS_ACCEPTED);
        $this->postJson("/api/v1/budgets/{$accepted->id}/follow-up-whatsapp")->assertUnprocessable();
    }

    public function test_daily_digest_is_sent_once_to_budget_managers(): void
    {
        Notification::fake();
        $this->budget(3);
        $this->budget(12, Budget::STATUS_SENT, ['effective_amount' => '500.00']);
        $stock = User::factory()->forClinic($this->clinic)->create();
        $stock->assignRole('stock');

        $service = app(BudgetFollowUpService::class);
        $this->assertGreaterThanOrEqual(1, $service->digestClinic($this->clinic));

        Notification::assertSentTo($this->admin, ForgottenBudgetsDigestNotification::class, function ($n) {
            $data = $n->toArray($this->admin);

            return $data['count'] === 2 && $data['oldest_days'] === 12 && $data['amount'] === '2000.00';
        });
        Notification::assertNotSentTo($stock, ForgottenBudgetsDigestNotification::class);

        // An empty config disables the alerts.
        $this->clinic->update(['settings' => ['budget_follow_up_days' => []]]);
        $this->assertSame(0, app(BudgetFollowUpService::class)->digestClinic($this->clinic->fresh()));
    }

    public function test_isolation_and_roles(): void
    {
        $budget = $this->budget(5);

        $other = Clinic::factory()->create();
        $foreign = User::factory()->forClinic($other)->create();
        $foreign->assignRole('admin');
        Sanctum::actingAs($foreign);
        CurrentClinic::forget();
        $this->getJson('/api/v1/budgets?forgotten=1')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/budgets/{$budget->id}/follow-up-whatsapp")->assertNotFound();

        foreach (['stock', 'professional'] as $role) {
            $user = User::factory()->forClinic($this->clinic)->create();
            $user->assignRole($role);
            Sanctum::actingAs($user);
            CurrentClinic::forget();
            $this->getJson('/api/v1/budgets?forgotten=1')->assertForbidden();
            $this->postJson("/api/v1/budgets/{$budget->id}/follow-up-whatsapp")->assertForbidden();
        }
    }
}
