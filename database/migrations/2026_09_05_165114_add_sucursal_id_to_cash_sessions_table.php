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
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('user_id')->constrained('sucursales')->nullOnDelete();
        });

        // Las cajas de antes de esta migración se abrieron todas contra la
        // única sucursal implícita de ese momento (misma que ya usaron las
        // migraciones de product_stocks y stock_adjustments).
        $sucursalId = DB::table('sucursales')->orderBy('id')->value('id');

        if ($sucursalId !== null) {
            DB::table('cash_sessions')->whereNull('sucursal_id')->update(['sucursal_id' => $sucursalId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_id');
        });
    }
};
