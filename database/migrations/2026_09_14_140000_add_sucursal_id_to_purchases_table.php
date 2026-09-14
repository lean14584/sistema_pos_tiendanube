<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * purchases no guardaba en qué sucursal se recibió la mercadería: al
     * crear una compra, StockAdjuster::apply() usaba la sucursal activa del
     * momento (correcto), pero Purchases\Edit y Purchases\Show::delete()
     * volvían a resolver CurrentSucursal::id() al revertir/reaplicar el
     * stock, en vez de usar la sucursal REAL de la compra — un admin global
     * que cambia de sucursal activa y después edita o borra una compra
     * vieja termina revirtiendo/reaplicando el stock en el local
     * equivocado. Mismo criterio que add_sucursal_id_to_invoices_table.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('provider_id')->constrained('sucursales')->nullOnDelete();
        });

        // Las compras de antes de esta migración se hicieron todas contra
        // la única sucursal implícita de ese momento.
        $sucursalId = DB::table('sucursales')->orderBy('id')->value('id');

        if ($sucursalId !== null) {
            DB::table('purchases')->whereNull('sucursal_id')->update(['sucursal_id' => $sucursalId]);
        }
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_id');
        });
    }
};
