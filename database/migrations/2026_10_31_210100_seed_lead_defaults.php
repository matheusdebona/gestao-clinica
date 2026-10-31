<?php

use App\Support\EnsureDefaultLeadStatuses;
use App\Support\EnsureRolesAndPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Adds leads.* / lead_statuses.manage to roles and default lead statuses for every
     * existing clinic. Idempotent; never removes clinic data.
     */
    public function up(): void
    {
        EnsureRolesAndPermissions::run();
        EnsureDefaultLeadStatuses::runAll();
    }

    public function down(): void
    {
        // Data seed only.
    }
};
