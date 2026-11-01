<?php

use App\Support\EnsureDefaultPatientTiers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Faixas de paciente (ex.: Bronze, Prata, Ouro, Diamante) pelo total comprado nos últimos 12 meses.
        // Cada faixa guarda só o valor mínimo; o máximo é o mínimo da próxima faixa menos R$ 0,01.
        Schema::create('patient_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('color', 20)->nullable();
            $table->decimal('min_amount', 15, 2);
            $table->timestamps();
            $table->unique(['clinic_id', 'min_amount']);
        });

        // Soma por paciente das vendas confirmadas na janela de 12 meses (lista, filtro e detalhe).
        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['clinic_id', 'client_id', 'status', 'sold_at'], 'sales_tier_spend_index');
        });

        // Clínicas que já existem recebem as faixas padrão; quem já tem faixas é ignorado.
        EnsureDefaultPatientTiers::runAll();
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_tier_spend_index');
        });
        Schema::dropIfExists('patient_tiers');
    }
};
