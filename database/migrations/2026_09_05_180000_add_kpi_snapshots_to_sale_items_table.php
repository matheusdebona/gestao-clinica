<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('product_name')->after('source_protocol_id');
            $table->decimal('list_unit_price', 15, 2)->after('quantity');
            $table->decimal('list_line_total', 15, 2)->after('list_unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'list_unit_price', 'list_line_total']);
        });
    }
};
