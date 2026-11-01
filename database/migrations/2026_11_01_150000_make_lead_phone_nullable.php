<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Telefone do lead passa a ser opcional (o lead pode ainda não ter passado o número).
 * Para agendar (converter o lead em paciente) o telefone continua obrigatório: isso fica
 * na validação da conversão, não no banco.
 *
 * O índice único parcial (clinic_id, phone_digits) WHERE cancelled_at IS NULL continua: NULL
 * não colide em índice único (Postgres e SQLite), então vários leads sem telefone convivem.
 * Linhas antigas com telefone vazio ficam com phone/phone_digits NULL.
 */
return new class extends Migration
{
    private const INDEX = 'leads_clinic_phone_active_unique';

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE leads ALTER COLUMN phone DROP NOT NULL');
        } else {
            Schema::table('leads', function (Blueprint $table): void {
                $table->string('phone', 30)->nullable()->change();
            });
            $this->restorePartialPhoneIndex();
        }

        DB::table('leads')->where('phone', '')->update(['phone' => null, 'phone_digits' => null]);
        DB::table('leads')->where('phone_digits', '')->update(['phone_digits' => null]);
    }

    public function down(): void
    {
        DB::table('leads')->whereNull('phone')->update(['phone' => '']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE leads ALTER COLUMN phone SET NOT NULL');
        } else {
            Schema::table('leads', function (Blueprint $table): void {
                $table->string('phone', 30)->nullable(false)->change();
            });
            $this->restorePartialPhoneIndex();
        }
    }

    /** SQLite rebuilds the table on ALTER and loses the WHERE of the partial unique index. */
    private function restorePartialPhoneIndex(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
        DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON leads (clinic_id, phone_digits) WHERE cancelled_at IS NULL');
    }
};
