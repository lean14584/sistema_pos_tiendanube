<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('product_id')->constrained('sucursales')->nullOnDelete();
        });

        // Los ajustes de antes de esta migración se hicieron todos contra la
        // única sucursal implícita de ese momento (la misma a la que la
        // migración de product_stocks le asignó el stock existente).
        $sucursalId = DB::table('sucursales')->orderBy('id')->value('id');

        if ($sucursalId !== null) {
            DB::table('stock_adjustments')->whereNull('sucursal_id')->update(['sucursal_id' => $sucursalId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_id');
        });
    }
};
