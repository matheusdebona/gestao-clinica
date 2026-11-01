<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `notifications.data` nasceu `text` (padrão do Laravel). No Postgres, os filtros por
 * `data->type` / `data->product_id` (dedupe dos avisos diários e filtro por categoria da
 * caixa de entrada) viram `"data"->>'type'`, que não existe para `text`
 * ("operator does not exist: text ->> unknown"). Em `jsonb` funcionam.
 *
 * Só Postgres: no SQLite o JSON é texto e `json_extract` já funciona.
 * Seguro com linhas: o Laravel sempre grava `json_encode(...)`; texto vazio vira `{}`.
 * Se houver JSON inválido o ALTER falha e a transação da migração desfaz tudo (nada muda).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('notifications')) {
            return;
        }

        if ($this->columnType() === 'jsonb') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE notifications
            ALTER COLUMN data TYPE jsonb
            USING (CASE WHEN btrim(data) = '' THEN '{}' ELSE data END)::jsonb
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('notifications')) {
            return;
        }

        if ($this->columnType() !== 'jsonb') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }

    private function columnType(): ?string
    {
        $row = DB::selectOne(
            "select data_type from information_schema.columns where table_schema = current_schema() and table_name = 'notifications' and column_name = 'data'"
        );

        return $row?->data_type;
    }
};
