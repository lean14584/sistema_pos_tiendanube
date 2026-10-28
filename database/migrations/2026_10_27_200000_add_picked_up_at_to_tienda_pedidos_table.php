<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separado de `status` (que es del pago, ver Checkout/InvoiceObserver):
     * un pedido puede estar pagado y todavía no retirado, o pagarse recién
     * en el mostrador al mismo momento que se retira — son dos cosas
     * independientes, no un paso más del mismo estado lineal.
     */
    public function up(): void
    {
        Schema::table('tienda_pedidos', function (Blueprint $table) {
            $table->timestamp('picked_up_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tienda_pedidos', function (Blueprint $table) {
            $table->dropColumn('picked_up_at');
        });
    }
};
