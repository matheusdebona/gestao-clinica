<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Retorno sugerido por protocolo + lista de retornos dos pacientes. */
    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table): void {
            $table->unsignedSmallInteger('return_after_days')->nullable();
        });

        Schema::create('patient_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('treatment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->date('due_on');
            $table->string('status', 20)->default('pending');
            $table->timestamp('done_at')->nullable();
            $table->string('done_reason', 20)->nullable();
            $table->foreignId('done_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->foreignId('whatsapp_sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sale_id', 'protocol_id']);
            $table->index(['clinic_id', 'status', 'due_on']);
            $table->index(['clinic_id', 'client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_returns');
        Schema::table('protocols', function (Blueprint $table): void {
            $table->dropColumn('return_after_days');
        });
    }
};
