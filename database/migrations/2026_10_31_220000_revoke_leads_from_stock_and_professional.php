<?php

use App\Support\EnsureRolesAndPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Owner decision: only admin, super-admin, receptionist and seller see leads.
     * Re-syncs role permissions (removes leads.view from stock and professional).
     * Per-user direct permissions are not used by the app and are left untouched.
     */
    public function up(): void
    {
        EnsureRolesAndPermissions::run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permission sync only.
    }
};
