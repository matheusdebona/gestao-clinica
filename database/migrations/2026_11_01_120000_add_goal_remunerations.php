<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remuneração por meta: valor fixo por ocorrência (ex.: R$ 80,00 por novo agendamento) e bônus
 * opcional ao bater a meta do mês. Sem linha = sem remuneração (nada muda para quem já usa).
 *
 * Os ganhos viram lançamentos em commission_entries (role = goal), ao lado das comissões de
 * venda e sessão, com o mesmo fluxo de "a pagar" e "pago".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_remunerations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 20);
            $table->decimal('per_occurrence_amount', 12, 2)->default(0);
            $table->decimal('target_bonus_amount', 12, 2)->default(0);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['clinic_id', 'metric']);
        });

        Schema::table('commission_entries', function (Blueprint $table): void {
            $table->foreignId('lead_id')->nullable()->after('appointment_id')->constrained()->nullOnDelete();
            $table->string('goal_metric', 20)->nullable()->after('lead_id');
            $table->string('goal_kind', 12)->nullable()->after('goal_metric');
            $table->index(['clinic_id', 'user_id', 'role', 'competence_month'], 'commission_entries_goal_index');
        });

        // Agendamento de avaliação criado na conversão do lead: se for cancelado, o agendamento
        // deixa de contar para a remuneração por meta.
        // No SQLite, uma FK nova recria a tabela e perde o índice único parcial de telefone
        // (leads_clinic_phone_active_unique); lá a coluna fica sem constraint.
        $withFk = DB::getDriverName() !== 'sqlite';
        Schema::table('leads', function (Blueprint $table) use ($withFk): void {
            $column = $table->foreignId('booked_appointment_id')->nullable()->after('booked_by_user_id');
            if ($withFk) {
                $column->constrained('appointments')->nullOnDelete();
            }
        });

        // Backfill: a avaliação (sem tratamento) criada junto com a conversão.
        DB::table('leads')->whereNotNull('converted_at')->whereNotNull('client_id')
            ->orderBy('id')
            ->select(['id', 'client_id', 'converted_at'])
            ->chunkById(200, function ($leads): void {
                foreach ($leads as $lead) {
                    $at = CarbonImmutable::parse($lead->converted_at);
                    $appointmentId = DB::table('appointments')
                        ->where('client_id', $lead->client_id)
                        ->whereNull('treatment_id')
                        ->whereBetween('created_at', [$at->subMinutes(5), $at->addMinutes(5)])
                        ->orderBy('id')
                        ->value('id');
                    if ($appointmentId !== null) {
                        DB::table('leads')->where('id', $lead->id)->update(['booked_appointment_id' => $appointmentId]);
                    }
                }
            });
    }

    public function down(): void
    {
        $withFk = DB::getDriverName() !== 'sqlite';
        Schema::table('leads', function (Blueprint $table) use ($withFk): void {
            if ($withFk) {
                $table->dropConstrainedForeignId('booked_appointment_id');
            } else {
                $table->dropColumn('booked_appointment_id');
            }
        });
        Schema::table('commission_entries', function (Blueprint $table): void {
            $table->dropIndex('commission_entries_goal_index');
            $table->dropConstrainedForeignId('lead_id');
            $table->dropColumn(['goal_metric', 'goal_kind']);
        });
        Schema::dropIfExists('goal_remunerations');
    }
};
