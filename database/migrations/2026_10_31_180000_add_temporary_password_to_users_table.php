<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('temporary_password_hash')->nullable()->after('password');
            $table->timestamp('temporary_password_expires_at')->nullable()->after('temporary_password_hash');
            $table->boolean('must_change_password')->default(false)->after('temporary_password_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['temporary_password_hash', 'temporary_password_expires_at', 'must_change_password']);
        });
    }
};
