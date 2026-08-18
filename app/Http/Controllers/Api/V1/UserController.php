<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\StoreUserRequest;
use App\Http\Requests\Api\V1\Users\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::query()->create($data);

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (! empty($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        return (new UserResource($user->load('clinic', 'roles', 'permissions')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('clinic', 'roles', 'permissions'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update(collect($data)->except(['roles', 'permissions'])->all());

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles'] ?? []);
        }

        if (array_key_exists('permissions', $data)) {
            $user->syncPermissions($data['permissions'] ?? []);
        }

        return new UserResource($user->fresh()->load('clinic', 'roles', 'permissions'));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->update(['is_active' => false]);

        return response()->json([
            'message' => 'User deactivated.',
        ]);
    }
}
