<?php

namespace Tests\Feature\Api\V1;

use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Sale;
use App\Models\User;
use App\Models\UserGoal;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserGoalTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create();
        $this->admin = User::factory()->forClinic($this->clinic)->create(['name' => 'Ana Admin']);
        $this->admin->assignRole('admin');
        $this->seller = User::factory()->forClinic($this->clinic)->create(['name' => 'Bruno Vendas']);
        $this->seller->assignRole('seller');
        CurrentClinic::setId($this->clinic->id);
        Carbon::setTestNow('2026-10-15 15:00:00');
        CarbonImmutable::setTestNow('2026-10-15 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        CurrentClinic::forget();
        parent::tearDown();
    }

    private function lead(array $attributes): void
    {
        $this->originId ??= ClientOrigin::factory()->create(['clinic_id' => $this->clinic->id])->id;
        $this->statusId ??= LeadStatus::factory()->create(['clinic_id' => $this->clinic->id])->id;
        Lead::factory()->forClinic($this->clinic)->create([
            'client_origin_id' => $this->originId,
            'lead_status_id' => $this->statusId,
            ...$attributes,
        ]);
    }

    private ?int $originId = null;

    private ?int $statusId = null;

    private function activity(): void
    {
        // 3 booked + 4 approached by the seller in October; 1 booked in September (ignored).
        foreach (range(1, 3) as $i) {
            $this->lead(['booked_at' => now()->subDays($i), 'booked_by_user_id' => $this->seller->id]);
        }
        $this->lead(['booked_at' => '2026-09-20 15:00:00', 'booked_by_user_id' => $this->seller->id]);
        foreach (range(1, 4) as $i) {
            $this->lead(['approached_at' => now()->subDays($i), 'approached_by_user_id' => $this->seller->id]);
        }
        Sale::factory()->forClinic($this->clinic)->create(['status' => Sale::STATUS_CONFIRMED, 'sold_by_user_id' => $this->seller->id, 'sold_at' => now()->subDays(2), 'effective_amount' => '1500.00']);
        Sale::factory()->forClinic($this->clinic)->create(['status' => Sale::STATUS_CONFIRMED, 'sold_by_user_id' => $this->seller->id, 'sold_at' => now()->subDays(5), 'effective_amount' => '1000.00']);
        Sale::factory()->forClinic($this->clinic)->create(['status' => Sale::STATUS_DRAFT, 'sold_by_user_id' => $this->seller->id, 'sold_at' => now(), 'effective_amount' => '9000.00']);
    }

    public function test_admin_sets_goal_and_seller_sees_own_progress(): void
    {
        $this->activity();
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/goals/{$this->seller->id}", [
            'month' => '2026-10', 'appointments_target' => 10, 'sales_target' => '10000', 'approached_target' => null,
        ])->assertOk()->assertJsonPath('data.has_goal', true);

        Sanctum::actingAs($this->seller);
        $data = $this->getJson('/api/v1/goals/me')->assertOk()->json('data');
        $metrics = collect($data['metrics'])->keyBy('key');
        $this->assertSame(3, $metrics['appointments']['actual']);
        $this->assertSame(30, $metrics['appointments']['percent']);
        $this->assertSame(7, $metrics['appointments']['remaining']);
        $this->assertSame('2500.00', $metrics['sales']['actual']);
        $this->assertSame(25, $metrics['sales']['percent']);
        $this->assertSame('7500.00', $metrics['sales']['remaining']);
        $this->assertSame(4, $metrics['approached']['actual']);
        $this->assertNull($metrics['approached']['target']);
        $this->assertSame(48, $data['expected_percent']); // 15/31

        // Next month inherits the goal.
        $this->getJson('/api/v1/goals/me?month=2026-11')->assertOk()
            ->assertJsonPath('data.inherited_from', '2026-10')
            ->assertJsonPath('data.targets.appointments_target', 10);

        // Non-admins can't edit nor see the team.
        $this->putJson("/api/v1/goals/{$this->seller->id}", ['month' => '2026-10', 'appointments_target' => 99, 'sales_target' => null, 'approached_target' => null])->assertForbidden();
        $this->getJson('/api/v1/goals')->assertForbidden();
    }

    public function test_team_view_validation_isolation_and_roles(): void
    {
        $this->activity();
        $stock = User::factory()->forClinic($this->clinic)->create();
        $stock->assignRole('stock');
        $pro = User::factory()->forClinic($this->clinic)->create();
        $pro->assignRole('professional');

        Sanctum::actingAs($this->admin);
        $rows = collect($this->getJson('/api/v1/goals?month=2026-10')->assertOk()->assertJsonPath('meta.can_edit', true)->json('data'));
        $this->assertTrue($rows->pluck('user.id')->contains($this->seller->id));
        $this->assertFalse($rows->pluck('user.id')->contains($stock->id));
        $this->assertFalse($rows->pluck('user.id')->contains($pro->id));

        $this->putJson("/api/v1/goals/{$this->seller->id}", ['month' => '10/2026', 'appointments_target' => -1, 'sales_target' => null, 'approached_target' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['month', 'appointments_target']);
        $this->putJson("/api/v1/goals/{$stock->id}", ['month' => '2026-10', 'appointments_target' => 1, 'sales_target' => null, 'approached_target' => null])
            ->assertStatus(422);

        foreach ([$stock, $pro] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/goals/me')->assertForbidden();
            $this->getJson('/api/v1/goals')->assertForbidden();
        }

        $other = Clinic::factory()->create();
        $foreign = User::factory()->forClinic($other)->create();
        $foreign->assignRole('admin');
        Sanctum::actingAs($foreign);
        CurrentClinic::forget();
        $this->putJson("/api/v1/goals/{$this->seller->id}", ['month' => '2026-10', 'appointments_target' => 1, 'sales_target' => null, 'approached_target' => null])->assertNotFound();
        $ids = collect($this->getJson('/api/v1/goals')->assertOk()->json('data'))->pluck('user.id');
        $this->assertFalse($ids->contains($this->seller->id));
        $this->assertSame(0, UserGoal::withoutGlobalScopes()->where('clinic_id', $other->id)->count());
    }
}
