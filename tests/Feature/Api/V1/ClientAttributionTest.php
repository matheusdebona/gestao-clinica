<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientAttributionTest extends TestCase
{
    use RefreshDatabase;

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
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    public function test_can_crud_client_origins(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/client-origins', [
            'name' => 'Instagram',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Instagram')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->getJson('/api/v1/client-origins')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Instagram');

        $this->putJson("/api/v1/client-origins/{$id}", [
            'name' => 'Instagram Ads',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Instagram Ads');

        $this->deleteJson("/api/v1/client-origins/{$id}")
            ->assertOk();

        $this->assertFalse(ClientOrigin::query()->findOrFail($id)->is_active);
    }

    public function test_can_crud_campaigns_scoped_to_origin(): void
    {
        Sanctum::actingAs($this->admin);
        $origin = ClientOrigin::factory()->forClinic($this->clinic)->create(['name' => 'Instagram']);

        $id = $this->postJson('/api/v1/campaigns', [
            'client_origin_id' => $origin->id,
            'name' => 'Reels Setembro',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Reels Setembro')
            ->assertJsonPath('data.client_origin_id', $origin->id)
            ->json('data.id');

        $this->getJson('/api/v1/campaigns?client_origin_id='.$origin->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->deleteJson("/api/v1/campaigns/{$id}")->assertOk();
        $this->assertFalse(Campaign::query()->findOrFail($id)->is_active);
    }

    public function test_can_create_and_update_client_with_attribution(): void
    {
        Sanctum::actingAs($this->admin);
        $origin = ClientOrigin::factory()->forClinic($this->clinic)->create(['name' => 'Instagram']);
        $campaign = Campaign::factory()->forOrigin($origin)->create(['name' => 'Black Friday']);

        $id = $this->postJson('/api/v1/clients', [
            'name' => 'Maria Silva',
            'whatsapp' => '11999887766',
            'client_origin_id' => $origin->id,
            'campaign_id' => $campaign->id,
            'initial_consultation_amount' => 150.5,
        ])->assertCreated()
            ->assertJsonPath('data.client_origin_id', $origin->id)
            ->assertJsonPath('data.campaign_id', $campaign->id)
            ->assertJsonPath('data.initial_consultation_amount', '150.50')
            ->assertJsonPath('data.client_origin.name', 'Instagram')
            ->assertJsonPath('data.campaign.name', 'Black Friday')
            ->json('data.id');

        $this->putJson("/api/v1/clients/{$id}", [
            'initial_consultation_amount' => 200,
        ])->assertOk()
            ->assertJsonPath('data.initial_consultation_amount', '200.00');
    }

    public function test_rejects_campaign_from_another_origin(): void
    {
        Sanctum::actingAs($this->admin);
        $instagram = ClientOrigin::factory()->forClinic($this->clinic)->create(['name' => 'Instagram']);
        $facebook = ClientOrigin::factory()->forClinic($this->clinic)->create(['name' => 'Facebook']);
        $campaign = Campaign::factory()->forOrigin($facebook)->create(['name' => 'FB Ads']);

        $this->postJson('/api/v1/clients', [
            'name' => 'João',
            'whatsapp' => '11988776655',
            'client_origin_id' => $instagram->id,
            'campaign_id' => $campaign->id,
            'initial_consultation_amount' => 100,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['campaign_id']);
    }

    public function test_rejects_inactive_origin_on_new_client(): void
    {
        Sanctum::actingAs($this->admin);
        $origin = ClientOrigin::factory()->forClinic($this->clinic)->inactive()->create();

        $this->postJson('/api/v1/clients', [
            'name' => 'João',
            'whatsapp' => '11988776655',
            'client_origin_id' => $origin->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['client_origin_id']);
    }

    public function test_deactivated_origin_keeps_historical_client_link(): void
    {
        Sanctum::actingAs($this->admin);
        CurrentClinic::setId($this->clinic->id);

        $origin = ClientOrigin::factory()->forClinic($this->clinic)->create();
        $client = Client::factory()->forClinic($this->clinic)->create([
            'client_origin_id' => $origin->id,
            'initial_consultation_amount' => '80.00',
        ]);

        $this->deleteJson("/api/v1/client-origins/{$origin->id}")->assertOk();

        $this->getJson("/api/v1/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.client_origin_id', $origin->id)
            ->assertJsonPath('data.initial_consultation_amount', '80.00');
    }

    public function test_catalogs_are_clinic_isolated(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/client-origins', ['name' => 'Instagram'])->assertCreated();

        $other = Clinic::factory()->create();
        $otherAdmin = User::factory()->forClinic($other)->create();
        $otherAdmin->assignRole('admin');
        Sanctum::actingAs($otherAdmin);
        CurrentClinic::setId($other->id);

        $this->getJson('/api/v1/client-origins')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_forbidden_without_catalog_or_client_write_permission(): void
    {
        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/client-origins')->assertForbidden();
        $this->getJson('/api/v1/campaigns')->assertForbidden();
    }

    public function test_clients_create_can_index_and_show_catalog_but_not_mutate(): void
    {
        $origin = ClientOrigin::factory()->forClinic($this->clinic)->create(['name' => 'Instagram']);
        $campaign = Campaign::factory()->forOrigin($origin)->create(['name' => 'Reels']);

        $creator = User::factory()->forClinic($this->clinic)->create();
        $creator->givePermissionTo('clients.create');
        Sanctum::actingAs($creator);

        $this->getJson('/api/v1/client-origins')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Instagram');
        $this->getJson("/api/v1/client-origins/{$origin->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $origin->id);

        $this->getJson('/api/v1/campaigns?client_origin_id='.$origin->id)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Reels');
        $this->getJson("/api/v1/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $campaign->id);

        $this->postJson('/api/v1/client-origins', ['name' => 'Google'])->assertForbidden();
        $this->putJson("/api/v1/client-origins/{$origin->id}", ['name' => 'IG'])->assertForbidden();
        $this->deleteJson("/api/v1/client-origins/{$origin->id}")->assertForbidden();

        $this->postJson('/api/v1/campaigns', [
            'client_origin_id' => $origin->id,
            'name' => 'Stories',
        ])->assertForbidden();
        $this->putJson("/api/v1/campaigns/{$campaign->id}", ['name' => 'Reels 2'])->assertForbidden();
        $this->deleteJson("/api/v1/campaigns/{$campaign->id}")->assertForbidden();
    }

    public function test_clients_update_can_index_catalog(): void
    {
        $origin = ClientOrigin::factory()->forClinic($this->clinic)->create(['name' => 'Facebook']);
        Campaign::factory()->forOrigin($origin)->create(['name' => 'FB Ads']);

        $editor = User::factory()->forClinic($this->clinic)->create();
        $editor->givePermissionTo('clients.update');
        Sanctum::actingAs($editor);

        $this->getJson('/api/v1/client-origins?active_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Facebook');
        $this->getJson('/api/v1/campaigns?active_only=1&client_origin_id='.$origin->id)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'FB Ads');

        $this->postJson('/api/v1/client-origins', ['name' => 'Indicação'])->assertForbidden();
        $this->postJson('/api/v1/campaigns', [
            'client_origin_id' => $origin->id,
            'name' => 'Nova',
        ])->assertForbidden();
    }

    public function test_receptionist_can_list_origins_and_campaigns_for_client_form(): void
    {
        $origin = ClientOrigin::factory()->forClinic($this->clinic)->create(['name' => 'Indicação']);
        Campaign::factory()->forOrigin($origin)->create(['name' => 'Amigos']);

        $receptionist = User::factory()->forClinic($this->clinic)->create();
        $receptionist->assignRole('receptionist');
        Sanctum::actingAs($receptionist);

        $this->getJson('/api/v1/client-origins?active_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Indicação');
        $this->getJson('/api/v1/campaigns?active_only=1&client_origin_id='.$origin->id)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Amigos');

        $this->postJson('/api/v1/client-origins', ['name' => 'TikTok'])->assertForbidden();
        $this->postJson('/api/v1/campaigns', [
            'client_origin_id' => $origin->id,
            'name' => 'Setembro',
        ])->assertForbidden();
    }
}
