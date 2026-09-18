<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En un "cambio" (Devolución + producto nuevo en la misma operación del
     * POS), la Venta nueva apunta acá a su Devolución hermana. Se usa una
     * columna propia en vez de reciclar `related_invoice_id` (esa es la
     * relación factura-original <-> Nota de Crédito; mezclarla acá haría que
     * la Venta del cambio quede bloqueada para su propia NC más adelante).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('cambio_devolucion_id')->nullable()->after('related_invoice_id')
                ->constrained('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cambio_devolucion_id');
        });
    }
};
