<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoice_items', 'quote_items'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('discount_percent', 5, 2)->default(0)->after('unit_price');
            });
        }
    }

    public function down(): void
    {
        foreach (['invoice_items', 'quote_items'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('discount_percent');
            });
        }
    }
};
