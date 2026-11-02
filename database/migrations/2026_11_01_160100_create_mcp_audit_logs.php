<?php

use App\Support\EnsureRolesAndPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conector MCP (Claude, só leitura): registro de cada chamada de ferramenta e a
 * permissão integrations.manage (tela Integrações; admin recebe por padrão).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('oauth_client_id')->nullable()->index();
            $table->string('tool', 80);
            $table->json('arguments')->nullable();
            $table->string('status', 10);
            $table->string('error', 255)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['clinic_id', 'created_at']);
            $table->index(['user_id', 'oauth_client_id', 'created_at']);
        });

        EnsureRolesAndPermissions::run();
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_logs');
    }
};
