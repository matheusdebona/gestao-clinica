<?php

namespace Tests\Feature\Api\V1;

use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\EnsureDefaultClientOrigins;
use App\Support\EnsureDefaultLeadStatuses;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadCommentEditTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected User $seller;

    protected User $otherSeller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->clinic = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($this->clinic);
        EnsureDefaultLeadStatuses::run($this->clinic);
        $this->admin = User::factory()->forClinic($this->clinic)->create(['name' => 'Ana Admin']);
        $this->admin->assignRole('admin');
        $this->seller = User::factory()->forClinic($this->clinic)->create(['name' => 'Bia Vendas']);
        $this->seller->assignRole('seller');
        $this->otherSeller = User::factory()->forClinic($this->clinic)->create(['name' => 'Caio Vendas']);
        $this->otherSeller->assignRole('seller');
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createLead(User $as, string $phone = '11933330000'): int
    {
        Sanctum::actingAs($as);
        $origin = ClientOrigin::query()->withoutGlobalScopes()
            ->where('clinic_id', $as->clinic_id)->where('name', 'Instagram')->firstOrFail();

        return $this->postJson('/api/v1/leads', [
            'name' => 'Marta', 'phone' => $phone, 'client_origin_id' => $origin->id,
        ])->assertCreated()->json('data.id');
    }

    private function comment(int $leadId, string $body): int
    {
        return $this->postJson("/api/v1/leads/{$leadId}/comments", ['body' => $body])->assertCreated()->json('data.id');
    }

    /** @return array{0:int,1:int,2:int} lead id, older comment id, latest comment id (both by the seller). */
    private function leadWithTwoSellerComments(): array
    {
        $lead = $this->createLead($this->seller);
        Carbon::setTestNow(now()->subMinutes(10));
        $older = $this->comment($lead, 'Primeiro contato');
        Carbon::setTestNow();
        $latest = $this->comment($lead, 'Pediu retorno amanha');

        return [$lead, $older, $latest];
    }

    public function test_author_can_edit_latest_comment_and_it_is_marked_as_edited(): void
    {
        [$lead, $older, $latest] = $this->leadWithTwoSellerComments();

        $this->patchJson("/api/v1/leads/{$lead}/comments/{$latest}", ['body' => '  '])
            ->assertUnprocessable()->assertJsonValidationErrors(['body']);

        $this->patchJson("/api/v1/leads/{$lead}/comments/{$latest}", ['body' => 'Pediu retorno amanhã às 10h'])
            ->assertOk()
            ->assertJsonPath('data.note', 'Pediu retorno amanhã às 10h')
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.user.name', 'Bia Vendas');

        $activities = collect($this->getJson("/api/v1/leads/{$lead}")->assertOk()->json('data.activities'))->keyBy('id');
        $this->assertNotNull($activities[$latest]['edited_at']);
        $this->assertTrue($activities[$latest]['can_edit']);
        $this->assertNull($activities[$older]['edited_at']);
        $this->assertFalse($activities[$older]['can_edit']);
        $this->assertArrayNotHasKey('can_edit', $activities->firstWhere('type', 'created'));
    }

    public function test_author_cannot_edit_older_comment(): void
    {
        [$lead, $older] = $this->leadWithTwoSellerComments();

        $this->patchJson("/api/v1/leads/{$lead}/comments/{$older}", ['body' => 'Alterado'])->assertForbidden();
        $this->assertDatabaseHas('lead_activities', ['id' => $older, 'note' => 'Primeiro contato', 'edited_at' => null]);
    }

    public function test_other_non_admin_user_cannot_edit(): void
    {
        [$lead, , $latest] = $this->leadWithTwoSellerComments();

        Sanctum::actingAs($this->otherSeller);
        $this->getJson("/api/v1/leads/{$lead}")->assertOk()->assertJsonPath('data.activities.0.can_edit', false);
        $this->patchJson("/api/v1/leads/{$lead}/comments/{$latest}", ['body' => 'Alterado'])->assertForbidden();

        // View-only users cannot edit either.
        $viewer = User::factory()->forClinic($this->clinic)->create();
        $viewer->givePermissionTo('leads.view');
        Sanctum::actingAs($viewer);
        $this->patchJson("/api/v1/leads/{$lead}/comments/{$latest}", ['body' => 'Alterado'])->assertForbidden();

        $this->assertDatabaseHas('lead_activities', ['id' => $latest, 'note' => 'Pediu retorno amanha']);
    }

    public function test_admin_can_edit_latest_comment_but_not_older_ones(): void
    {
        [$lead, $older, $latest] = $this->leadWithTwoSellerComments();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/leads/{$lead}/comments/{$older}", ['body' => 'Alterado'])->assertForbidden();
        $this->patchJson("/api/v1/leads/{$lead}/comments/{$latest}", ['body' => 'Corrigido pela admin'])
            ->assertOk()->assertJsonPath('data.note', 'Corrigido pela admin');
        $this->assertDatabaseHas('lead_activities', ['id' => $latest, 'edited_by_user_id' => $this->admin->id]);

        // A newer comment locks the previous latest one.
        $this->comment($lead, 'Novo comentário');
        Sanctum::actingAs($this->seller);
        $this->patchJson("/api/v1/leads/{$lead}/comments/{$latest}", ['body' => 'De novo'])->assertForbidden();
    }

    public function test_editing_follows_comment_rules_on_cancelled_leads(): void
    {
        [$lead, , $latest] = $this->leadWithTwoSellerComments();
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/leads/{$lead}/cancel", ['reason' => 'Desistiu'])->assertOk();

        // Comments stay allowed on cancelled leads, so the latest comment stays editable.
        Sanctum::actingAs($this->seller);
        $this->patchJson("/api/v1/leads/{$lead}/comments/{$latest}", ['body' => 'Desistiu de vez'])->assertOk();
    }

    public function test_comment_from_another_clinic_is_not_reachable(): void
    {
        [$lead, , $latest] = $this->leadWithTwoSellerComments();

        $other = Clinic::factory()->create();
        EnsureDefaultClientOrigins::run($other);
        EnsureDefaultLeadStatuses::run($other);
        $foreignAdmin = User::factory()->forClinic($other)->create();
        $foreignAdmin->assignRole('admin');
        $foreignLead = $this->createLead($foreignAdmin, '11977776666');

        $this->patchJson("/api/v1/leads/{$lead}/comments/{$latest}", ['body' => 'Invasão'])->assertNotFound();
        // Own lead id + foreign comment id: still not reachable.
        $this->patchJson("/api/v1/leads/{$foreignLead}/comments/{$latest}", ['body' => 'Invasão'])->assertNotFound();

        $this->assertDatabaseHas('lead_activities', ['id' => $latest, 'note' => 'Pediu retorno amanha']);
    }

    public function test_non_comment_activities_cannot_be_edited(): void
    {
        $lead = $this->createLead($this->admin);
        $created = Lead::query()->findOrFail($lead)->activities()->first();

        $this->patchJson("/api/v1/leads/{$lead}/comments/{$created->id}", ['body' => 'x'])->assertNotFound();
    }
}
