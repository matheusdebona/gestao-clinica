<?php

use App\Support\EnsureDefaultExpenseCategories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo de despesas: categorias por natureza, lançamentos por competência,
     * despesas recorrentes geradas todo mês e equipamentos com depreciação.
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 20);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['clinic_id', 'name']);
            $table->index(['clinic_id', 'kind']);
        });

        Schema::create('equipment_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->decimal('purchase_value', 15, 2);
            $table->decimal('residual_value', 15, 2)->default(0);
            $table->date('purchased_on')->nullable();
            $table->unsignedSmallInteger('useful_life_months');
            $table->date('depreciation_start_month');
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('description', 160);
            $table->decimal('amount', 15, 2);
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->date('starts_month');
            $table->date('ends_month')->nullable();
            $table->string('supplier', 160)->nullable();
            $table->foreignId('client_origin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('description', 160);
            $table->decimal('amount', 15, 2);
            $table->date('competence_month');
            $table->date('due_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('supplier', 160)->nullable();
            $table->foreignId('recurring_expense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('equipment_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_origin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'competence_month']);
            $table->unique(['recurring_expense_id', 'competence_month']);
            $table->unique(['equipment_asset_id', 'competence_month']);
        });

        EnsureDefaultExpenseCategories::runAll();
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('equipment_assets');
        Schema::dropIfExists('expense_categories');
    }
};
