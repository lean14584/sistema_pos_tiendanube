<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->integer('quantity_received')->nullable()->after('quantity');
        });

        // Los ítems de envíos ya marcados "recibido" por la migración
        // hermana: la cantidad recibida coincide con la enviada (se
        // acreditaron juntas en su momento).
        DB::table('stock_transfer_items')->update([
            'quantity_received' => DB::raw('quantity'),
        ]);
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn('quantity_received');
        });
    }
};
