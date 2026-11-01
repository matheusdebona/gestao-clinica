<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ChangeTemporaryPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\RegisterClinicAdminService;
use App\Services\TemporaryPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterClinicAdminService $registrar): JsonResponse
    {
        $user = $registrar->register($request->validated());
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 201);
    }

    public const FORGOT_PASSWORD_MESSAGE = 'Se houver uma conta com este e-mail, enviamos uma senha temporária. Confira sua caixa de entrada e o spam.';

    public function login(LoginRequest $request, TemporaryPasswordService $temporaryPasswords): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        $password = $request->string('password')->toString();
        $usedTemporary = false;

        if ($user && Hash::check($password, $user->password)) {
            if ($temporaryPasswords->passwordIsTemporary($user)) {
                // The password is the one e-mailed by "Esqueci a senha".
                if (! $temporaryPasswords->hasValidTemporaryPassword($user)) {
                    throw ValidationException::withMessages([
                        'email' => ['A senha temporária expirou. Peça uma nova em “Esqueci a senha”.'],
                    ]);
                }
                $usedTemporary = true;
            } elseif ($user->temporary_password_hash !== null || $user->must_change_password) {
                // Password was set some other way: drop leftovers.
                $temporaryPasswords->clear($user, alsoFlag: true);
            }
        } elseif ($user && $temporaryPasswords->matches($user, $password)) {
            // Legacy pending temporary password issued before it replaced the password.
            $usedTemporary = true;
        } else {
            if ($user) {
                $temporaryPasswords->clearIfExpired($user);
            }

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('Your account is inactive.')],
            ]);
        }

        if ($usedTemporary && ! $user->must_change_password) {
            $user->forceFill(['must_change_password' => true])->save();
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('clinic', 'roles', 'permissions')),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request, TemporaryPasswordService $temporaryPasswords): JsonResponse
    {
        $temporaryPasswords->request($request->string('email')->toString());

        return response()->json([
            'message' => self::FORGOT_PASSWORD_MESSAGE,
        ]);
    }

    public function changeTemporaryPassword(
        ChangeTemporaryPasswordRequest $request,
        TemporaryPasswordService $temporaryPasswords,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if (! $temporaryPasswords->matches($user, $request->string('temporary_password')->toString())) {
            $expired = $user->temporary_password_hash === null || ! $temporaryPasswords->hasValidTemporaryPassword($user);

            throw ValidationException::withMessages([
                'temporary_password' => [$expired
                    ? 'A senha temporária expirou. Saia e peça uma nova em “Esqueci a senha”.'
                    : 'Senha temporária incorreta.'],
            ]);
        }

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'temporary_password_hash' => null,
            'temporary_password_expires_at' => null,
            'must_change_password' => false,
        ])->save();

        // Sign out every other session; keep the current one.
        $current = $user->currentAccessToken();
        $user->tokens()
            ->when(
                $current instanceof PersonalAccessToken,
                fn ($query) => $query->whereKeyNot($current->getKey()),
            )
            ->delete();

        return response()->json([
            'message' => 'Senha alterada.',
            'user' => new UserResource($user->load('clinic', 'roles', 'permissions')),
        ]);
    }

    public function me(Request $request): UserResource
    {
        $user = $request->user()->load('clinic', 'roles', 'permissions');

        return new UserResource($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'All sessions revoked.',
        ]);
    }
}
