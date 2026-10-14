<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuánto se descontó de este pago puntual por el descuento de medio de
     * pago (efectivo/transferencia, ver CompanySettings::descuentoPctParaMedioDePago).
     * `amount` ya guarda el monto real cobrado (neto de ese descuento); este
     * campo es solo para poder mostrar el ahorro como línea separada en el
     * ticket, por medio de pago (ver TicketPrinterService::armarPagos()).
     */
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });
    }
};
