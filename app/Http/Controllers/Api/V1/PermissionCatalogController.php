<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\EnsureRolesAndPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionCatalogController extends Controller
{
    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->pluck('name');

        return response()->json(['data' => $permissions]);
    }

    public function roles(Request $request): JsonResponse
    {
        EnsureRolesAndPermissions::run();

        $query = Role::query()
            ->with('permissions:id,name')
            ->orderBy('name');

        if ($request->boolean('assignable')) {
            $query->whereIn('name', EnsureRolesAndPermissions::ASSIGNABLE_ROLES);
        }

        $roles = $query
            ->get()
            ->map(fn (Role $role) => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
            ]);

        return response()->json(['data' => $roles]);
    }
}
