<?php

use App\Services\PaymentFeeService;
use App\Support\EnsureRolesAndPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Fase 1 da margem de contribuição:
     * - taxa e líquido gravados em cada pagamento (regra de cartão > taxa do método);
     * - taxa de antecipação (% ao mês) por operadora;
     * - regime tributário por competência (tax_settings);
     * - motivo de perda valorizada nas saídas de estoque;
     * - permissões finance.view / finance.manage (admin) e retirada de card_fees.manage do vendedor.
     */
    public function up(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->string('fee_source', 16)->nullable()->after('paid_at');
            $table->decimal('fee_percent_applied', 8, 4)->nullable()->after('fee_source');
            $table->decimal('fee_fixed_applied', 12, 2)->nullable()->after('fee_percent_applied');
            $table->decimal('fee_amount', 15, 2)->default(0)->after('fee_fixed_applied');
            $table->decimal('anticipation_percent_applied', 8, 4)->nullable()->after('fee_amount');
            $table->decimal('anticipation_fee_amount', 15, 2)->default(0)->after('anticipation_percent_applied');
            $table->decimal('net_amount', 15, 2)->nullable()->after('anticipation_fee_amount');
        });

        Schema::table('card_operators', function (Blueprint $table) {
            $table->decimal('anticipation_rate_percent', 8, 4)->nullable()->after('auto_anticipate');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('loss_reason', 20)->nullable()->after('reason');
            $table->decimal('loss_value', 15, 2)->nullable()->after('loss_reason');
            $table->index(['clinic_id', 'loss_reason', 'created_at']);
        });

        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->string('regime', 20);
            $table->decimal('simples_rate_percent', 8, 4)->nullable();
            $table->decimal('iss_percent', 8, 4)->nullable();
            $table->decimal('pis_percent', 8, 4)->nullable();
            $table->decimal('cofins_percent', 8, 4)->nullable();
            $table->decimal('irpj_percent', 8, 4)->nullable();
            $table->decimal('csll_percent', 8, 4)->nullable();
            $table->decimal('other_percent', 8, 4)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'effective_from']);
        });

        EnsureRolesAndPermissions::run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(PaymentFeeService::class)->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_settings');

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['clinic_id', 'loss_reason', 'created_at']);
            $table->dropColumn(['loss_reason', 'loss_value']);
        });

        Schema::table('card_operators', function (Blueprint $table) {
            $table->dropColumn('anticipation_rate_percent');
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropColumn([
                'fee_source', 'fee_percent_applied', 'fee_fixed_applied', 'fee_amount',
                'anticipation_percent_applied', 'anticipation_fee_amount', 'net_amount',
            ]);
        });
    }
};
