<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->string('status', 50)->default('issued');
            $table->string('storage_path');
            $table->string('filename');
            $table->string('mime_type', 100)->default('application/pdf');
            $table->json('payload');
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'type']);
            $table->index(['clinic_id', 'budget_id']);
            $table->index(['clinic_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
