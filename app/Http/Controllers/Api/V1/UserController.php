<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\StoreUserRequest;
use App\Http\Requests\Api\V1\Users\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\CurrentClinic;
use App\Support\EnsureRolesAndPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->with(['clinic', 'roles', 'permissions'])
            ->orderBy('name')
            ->paginate(20);

        return UserResource::collection($users);
    }

    /**
     * Active clinic users who can run a session (professional or admin).
     */
    public function professionals(): AnonymousResourceCollection
    {
        $users = User::query()
            ->where('is_active', true)
            ->role(['professional', 'admin'])
            ->orderBy('name')
            ->get();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        EnsureRolesAndPermissions::run();

        $data = $request->validated();
        unset($data['clinic_id'], $data['permissions'], $data['password_confirmation']);

        $clinicId = CurrentClinic::id();
        if ($clinicId === null) {
            throw new HttpException(422, 'Clínica não resolvida.');
        }

        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $this->assertAssignableRoles($roles);

        $data['clinic_id'] = $clinicId;
        $data['is_active'] = $data['is_active'] ?? true;

        $user = User::query()->create($data);
        $user->syncRoles($roles);

        return (new UserResource($user->load('clinic', 'roles', 'permissions')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->ensureClinicUser($user);

        return new UserResource($user->load('clinic', 'roles', 'permissions'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->ensureClinicUser($user);
        EnsureRolesAndPermissions::run();

        $data = $request->validated();
        unset($data['clinic_id'], $data['permissions'], $data['password_confirmation']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if (array_key_exists('roles', $data)) {
            $roles = $data['roles'] ?? [];
            unset($data['roles']);

            if ($user->hasRole('admin')) {
                throw ValidationException::withMessages([
                    'roles' => ['Não é permitido alterar os papéis do administrador da clínica.'],
                ]);
            }

            $this->assertAssignableRoles($roles);
            $user->syncRoles($roles);
        }

        $user->update($data);

        return new UserResource($user->fresh()->load('clinic', 'roles', 'permissions'));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->ensureClinicUser($user);

        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'user' => ['Você não pode desativar a própria conta.'],
            ]);
        }

        if ($user->hasRole('admin')) {
            throw ValidationException::withMessages([
                'user' => ['Não é permitido desativar o administrador da clínica.'],
            ]);
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'message' => 'User deactivated.',
        ]);
    }

    private function ensureClinicUser(User $user): void
    {
        $clinicId = CurrentClinic::id() ?? auth()->user()?->clinic_id;

        if ($clinicId === null || (int) $user->clinic_id !== (int) $clinicId) {
            abort(404);
        }
    }

    /**
     * @param  list<string>  $roles
     */
    private function assertAssignableRoles(array $roles): void
    {
        if ($roles === []) {
            throw ValidationException::withMessages([
                'roles' => ['Selecione ao menos um papel.'],
            ]);
        }

        foreach ($roles as $role) {
            if (! EnsureRolesAndPermissions::isAssignable($role)) {
                throw ValidationException::withMessages([
                    'roles' => ["O papel \"{$role}\" não pode ser atribuído."],
                ]);
            }
        }
    }
}
