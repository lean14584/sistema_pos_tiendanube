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
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('client_id')->constrained('sucursales')->nullOnDelete();
        });

        // Las facturas de antes de esta migración se hicieron todas contra
        // la única sucursal implícita de ese momento (mismo criterio que ya
        // usaron product_stocks, stock_adjustments y cash_sessions).
        $sucursalId = DB::table('sucursales')->orderBy('id')->value('id');

        if ($sucursalId !== null) {
            DB::table('invoices')->whereNull('sucursal_id')->update(['sucursal_id' => $sucursalId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_id');
        });
    }
};
