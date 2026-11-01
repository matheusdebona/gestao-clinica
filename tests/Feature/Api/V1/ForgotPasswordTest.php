<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Models\Clinic;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use App\Support\TemporaryPasswordGenerator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'NovaSenha#2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function makeUser(array $attributes = []): User
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->forClinic($clinic)->create(array_merge([
            'email' => 'admin@clinica.test',
            'password' => 'SenhaAtual#2026',
        ], $attributes));
        $user->assignRole('admin');

        return $user;
    }

    /** Request a reset and return the temporary password captured from the e-mail. */
    private function requestTemporaryPassword(string $email = 'admin@clinica.test'): string
    {
        $plain = null;
        Notification::fake();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])->assertOk();
        Notification::assertSentTo(
            User::where('email', $email)->firstOrFail(),
            TemporaryPasswordNotification::class,
            function (TemporaryPasswordNotification $n) use (&$plain) {
                $plain = $n->temporaryPassword;

                return true;
            },
        );

        return $plain;
    }

    private function loginWith(string $password, string $email = 'admin@clinica.test')
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
    }

    public function test_forgot_password_returns_same_neutral_message_for_unknown_email(): void
    {
        Notification::fake();
        $this->makeUser();

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'admin@clinica.test']);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ninguem@clinica.test']);

        $known->assertOk()->assertExactJson(['message' => AuthController::FORGOT_PASSWORD_MESSAGE]);
        $unknown->assertOk()->assertExactJson(['message' => AuthController::FORGOT_PASSWORD_MESSAGE]);
        Notification::assertSentTimes(TemporaryPasswordNotification::class, 1);
    }

    public function test_forgot_password_validates_email_in_portuguese(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nao-e-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_inactive_user_gets_no_email(): void
    {
        Notification::fake();
        $this->makeUser(['is_active' => false]);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'admin@clinica.test'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_request_replaces_current_password_immediately(): void
    {
        $user = $this->makeUser();
        $original = $user->password;

        $plain = $this->requestTemporaryPassword();
        $user->refresh();

        $this->assertNotSame($original, $user->password);
        $this->assertTrue(Hash::check($plain, $user->password));
        $this->assertFalse(Hash::check('SenhaAtual#2026', $user->password));
        $this->assertSame($user->password, $user->temporary_password_hash);
        $this->assertEqualsWithDelta(60, now()->diffInMinutes($user->temporary_password_expires_at), 1);
        $this->assertTrue($user->must_change_password);

        // Old password stops working; the e-mailed one works right away and forces a change.
        $this->loginWith('SenhaAtual#2026')->assertStatus(422);
        $this->loginWith($plain)->assertOk()->assertJsonPath('user.must_change_password', true);
        // Still the temporary password after a failed attempt with the old one.
        $this->loginWith($plain)->assertOk();
    }

    public function test_full_flow_old_password_dead_temporary_works_then_new_password(): void
    {
        $this->makeUser();
        $plain = $this->requestTemporaryPassword();

        $this->loginWith('SenhaAtual#2026')->assertStatus(422);
        $token = $this->loginWith($plain)->assertOk()->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/change-temporary-password', [
            'temporary_password' => $plain,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->app['auth']->forgetGuards();

        $this->loginWith($plain)->assertStatus(422);
        $this->loginWith('SenhaAtual#2026')->assertStatus(422);
        $this->loginWith(self::NEW_PASSWORD)->assertOk()->assertJsonPath('user.must_change_password', false);
    }

    public function test_temporary_password_is_short_and_readable(): void
    {
        $seen = [];
        for ($i = 0; $i < 300; $i++) {
            $plain = TemporaryPasswordGenerator::generate();
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $plain);
            $this->assertDoesNotMatchRegularExpression('/[0O1IL]/', $plain);
            $seen[$plain] = true;
        }
        $this->assertCount(300, $seen);
    }

    public function test_temporary_password_login_tolerates_spaces_and_lowercase(): void
    {
        $this->makeUser();
        $plain = $this->requestTemporaryPassword();
        $typed = '  '.strtolower(substr($plain, 0, 4)).' '.substr($plain, 4).' ';

        $this->loginWith('SenhaAtual#2026')->assertStatus(422);
        $token = $this->loginWith($typed)->assertOk()->assertJsonPath('user.must_change_password', true)->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/change-temporary-password', [
            'temporary_password' => strtolower($plain).' ',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->loginWith($typed)->assertStatus(422);
        $this->loginWith(self::NEW_PASSWORD)->assertOk();
    }

    public function test_email_is_ptbr_branded_and_links_to_frontend(): void
    {
        config(['app.frontend_url' => 'https://app.hofpay.com.br']);
        $user = $this->makeUser(['name' => 'Ana']);

        $mail = (new TemporaryPasswordNotification('Kp7m-X3qr-9tZa', 60))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Sua senha temporária — HOF Pay', $mail->subject);
        $this->assertStringContainsString('Kp7m-X3qr-9tZa', $html);
        $this->assertStringContainsString('60 minutos', $html);
        $this->assertStringContainsString('https://app.hofpay.com.br/login?email=admin%40clinica.test', $html);
        $this->assertStringContainsString('https://app.hofpay.com.br/icon-192.png', $html);
        $this->assertStringNotContainsString('api.hofpay.com.br', $html);
        $this->assertStringContainsString('Olá, Ana', $html);
        $this->assertInstanceOf(ShouldQueue::class, new TemporaryPasswordNotification('x', 60));
    }

    public function test_login_with_temporary_password_forces_change_and_blocks_app(): void
    {
        $this->makeUser();
        $plain = $this->requestTemporaryPassword();

        $login = $this->loginWith($plain)
            ->assertOk()
            ->assertJsonPath('user.must_change_password', true);
        $token = $login->json('token');

        $this->withToken($token)->getJson('/api/v1/clinics/current')
            ->assertStatus(403)
            ->assertJsonPath('code', 'password_change_required');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);
    }

    public function test_change_temporary_password_sets_new_password_and_revokes_other_sessions(): void
    {
        $user = $this->makeUser();
        $otherToken = $user->createToken('api')->plainTextToken;
        $plain = $this->requestTemporaryPassword();
        $token = $this->loginWith($plain)->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/change-temporary-password', [
            'temporary_password' => $plain,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk()->assertJsonPath('user.must_change_password', false);

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertNull($user->temporary_password_hash);
        $this->assertNull($user->temporary_password_expires_at);
        $this->assertFalse($user->must_change_password);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => hash('sha256', explode('|', $otherToken)[1])]);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/clinics/current')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->loginWith($plain)->assertStatus(422);
        $this->loginWith('SenhaAtual#2026')->assertStatus(422);
        $this->loginWith(self::NEW_PASSWORD)->assertOk();
    }

    public function test_change_requires_correct_temporary_password_and_strong_new_password(): void
    {
        $this->makeUser();
        $plain = $this->requestTemporaryPassword();
        $token = $this->loginWith($plain)->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/change-temporary-password', [
            'temporary_password' => 'Errada-Errada-1',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertStatus(422)->assertJsonValidationErrors(['temporary_password']);

        $this->withToken($token)->postJson('/api/v1/auth/change-temporary-password', [
            'temporary_password' => $plain,
            'password' => 'fraca',
            'password_confirmation' => 'fraca',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->withToken($token)->postJson('/api/v1/auth/change-temporary-password', [
            'temporary_password' => $plain,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'Outra#Senha2026',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_new_password_must_differ_from_temporary(): void
    {
        $this->makeUser();
        $plain = $this->requestTemporaryPassword();
        $token = $this->loginWith($plain)->json('token');
        $strongLikeTemp = $plain.'#';

        // Same value as the temporary password is rejected.
        $this->withToken($token)->postJson('/api/v1/auth/change-temporary-password', [
            'temporary_password' => $strongLikeTemp,
            'password' => $strongLikeTemp,
            'password_confirmation' => $strongLikeTemp,
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->assertStringContainsString(
            'diferente da senha temporária',
            $this->withToken($token)->postJson('/api/v1/auth/change-temporary-password', [
                'temporary_password' => $strongLikeTemp,
                'password' => $strongLikeTemp,
                'password_confirmation' => $strongLikeTemp,
            ])->json('errors.password.0'),
        );
    }

    public function test_expired_temporary_password_cannot_login(): void
    {
        $this->makeUser();
        $plain = $this->requestTemporaryPassword();

        $this->travel(61)->minutes();

        $this->loginWith($plain)->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'A senha temporária expirou. Peça uma nova em “Esqueci a senha”.');
        $this->loginWith('SenhaAtual#2026')->assertStatus(422);

        // A new request lets the person in again.
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'admin@clinica.test'])->assertOk();
    }

    public function test_new_request_replaces_previous_temporary_password(): void
    {
        $this->makeUser();
        $first = $this->requestTemporaryPassword();
        $second = $this->requestTemporaryPassword();

        $this->assertNotSame($first, $second);
        $this->loginWith($first)->assertStatus(422);
        $this->loginWith($second)->assertOk()->assertJsonPath('user.must_change_password', true);
    }

    public function test_password_set_elsewhere_clears_leftover_temporary_state(): void
    {
        $user = $this->makeUser();
        $this->requestTemporaryPassword();
        // e.g. admin resets the password from the team screen
        $user->refresh()->forceFill(['password' => 'OutraSenha#2026'])->save();

        $this->loginWith('OutraSenha#2026')->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->assertNull($user->refresh()->temporary_password_hash);
    }

    public function test_per_email_throttle_is_silent(): void
    {
        Notification::fake();
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'admin@clinica.test'])
                ->assertOk()
                ->assertExactJson(['message' => AuthController::FORGOT_PASSWORD_MESSAGE]);
        }

        Notification::assertSentTimes(TemporaryPasswordNotification::class, 3);
    }

    public function test_per_ip_throttle_on_forgot_password_route(): void
    {
        $this->withMiddleware(ThrottleRequests::class);
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => "x{$i}@clinica.test"])->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'x9@clinica.test'])->assertStatus(429);
    }

    public function test_prune_command_keeps_marker_while_password_is_the_expired_temporary(): void
    {
        $user = $this->makeUser();
        $this->requestTemporaryPassword();
        $this->travel(61)->minutes();

        $this->artisan('auth:prune-temporary-passwords')->assertSuccessful();
        $this->assertNotNull($user->refresh()->temporary_password_hash);

        $user->forceFill(['password' => 'OutraSenha#2026'])->save();
        $this->artisan('auth:prune-temporary-passwords')->assertSuccessful();
        $this->assertNull($user->refresh()->temporary_password_hash);
    }
}
