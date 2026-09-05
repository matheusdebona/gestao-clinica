<?php

namespace Tests\Feature\Api\V1;

use App\Models\Clinic;
use App\Models\User;
use App\Support\CurrentClinic;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->clinic = Clinic::factory()->create(['name' => 'Clínica Alpha']);
        $this->admin = User::factory()->forClinic($this->clinic)->create();
        $this->admin->assignRole('admin');
        CurrentClinic::setId($this->clinic->id);
        Storage::fake('s3');
    }

    protected function tearDown(): void
    {
        CurrentClinic::forget();
        parent::tearDown();
    }

    public function test_can_get_default_branding(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/clinic/branding')
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Clínica Alpha')
            ->assertJsonPath('data.primary_color', '#0F766E')
            ->assertJsonPath('data.secondary_color', '#134E4A')
            ->assertJsonPath('data.has_logo', false);
    }

    public function test_can_update_branding_colors_and_name(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/v1/clinic/branding', [
            'display_name' => 'Estética Nova',
            'primary_color' => '#0EA5E9',
            'secondary_color' => '#0369A1',
        ])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Estética Nova')
            ->assertJsonPath('data.primary_color', '#0EA5E9')
            ->assertJsonPath('data.secondary_color', '#0369A1');

        $this->assertSame('Estética Nova', $this->clinic->fresh()->settings['branding']['display_name']);
    }

    public function test_rejects_invalid_hex_color(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/v1/clinic/branding', [
            'primary_color' => 'teal',
        ])->assertStatus(422);
    }

    public function test_can_upload_and_delete_logo(): void
    {
        Sanctum::actingAs($this->admin);

        // Minimal 1x1 PNG (avoids needing PHP GD for UploadedFile::fake()->image())
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('logo.png', $png);

        $this->post('/api/v1/clinic/branding/logo', [
            'logo' => $file,
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.has_logo', true);

        $path = $this->clinic->fresh()->settings['branding']['logo_path'];
        $this->assertNotNull($path);
        Storage::disk('s3')->assertExists($path);

        $this->deleteJson('/api/v1/clinic/branding/logo')
            ->assertOk()
            ->assertJsonPath('data.has_logo', false)
            ->assertJsonPath('data.logo_path', null);

        Storage::disk('s3')->assertMissing($path);
    }

    public function test_forbidden_without_branding_permission(): void
    {
        $user = User::factory()->forClinic($this->clinic)->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/clinic/branding')->assertForbidden();
        $this->putJson('/api/v1/clinic/branding', [
            'display_name' => 'X',
        ])->assertForbidden();
    }
}
