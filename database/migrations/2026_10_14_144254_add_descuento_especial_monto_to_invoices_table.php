<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monto NETO (sin IVA) que "Mis Descuentos" le restó a la venta, guardado
     * aparte del `discount_percent` ya compuesto en cada InvoiceItem (que
     * sigue mezclando manual+promo+especial+medio de pago para que el
     * neto/IVA de AFIP salga bien). Este campo es solo para poder mostrar esa
     * porción como línea separada en el ticket (ver TicketPrinterService).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('descuento_especial_monto', 12, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('descuento_especial_monto');
        });
    }
};
