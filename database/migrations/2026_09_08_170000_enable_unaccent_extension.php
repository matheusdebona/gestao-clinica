<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

return new class extends Migration
{
    /**
     * Enable Postgres `unaccent` for accent-insensitive `?q=` search.
     *
     * `CREATE EXTENSION` often needs a superuser. Official `postgres:18` images
     * allow it; some managed Postgres providers require enabling `unaccent` in
     * the dashboard (or as a privileged role) before this succeeds.
     *
     * Search falls back to `translate()` of common Latin diacritics when the
     * extension is missing. Tests on SQLite skip this migration body.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.immutable_unaccent(text)
RETURNS text
LANGUAGE sql
IMMUTABLE PARALLEL SAFE STRICT
AS $$
    SELECT public.unaccent('public.unaccent'::regdictionary, $1)
$$;
SQL);
        } catch (Throwable) {
            // Extension may already exist under another role, or CREATE EXTENSION
            // may be forbidden. CaseInsensitiveSearch falls back to translate().
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::unprepared('DROP FUNCTION IF EXISTS public.immutable_unaccent(text)');
        } catch (Throwable) {
            // Ignore: function may not have been created.
        }
    }
};
