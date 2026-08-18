<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
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

    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
            ]);

        return response()->json(['data' => $roles]);
    }
}
