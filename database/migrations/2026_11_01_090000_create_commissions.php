<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regras de comissão (vendedor por venda, profissional por sessão; % ou fixo; geral ou por protocolo)
     * e os lançamentos gerados a partir de vendas confirmadas e sessões concluídas.
     */
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('calc_type', 10);
            $table->decimal('value', 12, 4);
            $table->string('base', 10)->default('gross');
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'role', 'is_active']);
        });

        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20);
            $table->foreignId('sale_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_id')->nullable()->constrained()->nullOnDelete();
            $table->date('competence_month');
            $table->decimal('base_amount', 15, 2);
            $table->decimal('rate_percent', 8, 4)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status', 12)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'competence_month']);
            $table->index(['sale_id', 'role']);
            $table->index(['appointment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('commission_rules');
    }
};
