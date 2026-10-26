<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo inverso al pedido web que originó esta factura/remito (mismo
     * patrón que tiendanube_order_id/mp_external_reference) — permite
     * encontrar la factura fiscal real a partir del Remito original sin
     * pasar por tienda_pedidos.invoice_id cuando ya se tiene la Invoice en
     * mano (ej. al facturar el remito tras el pago, ver FacturarRemito).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('tienda_pedido_id')->nullable()->after('tiendanube_order_id')->constrained('tienda_pedidos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tienda_pedido_id');
        });
    }
};
