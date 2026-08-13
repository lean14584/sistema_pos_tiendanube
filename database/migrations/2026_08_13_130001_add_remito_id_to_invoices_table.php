<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula una factura con el remito (nota de entrega) del que se generó.
     * Permite el flujo "entrego con remito, después facturo": la factura
     * copia los ítems del remito y NO vuelve a mover stock (ya lo movió el
     * remito). Un remito se factura una sola vez.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('remito_id')->nullable()->after('related_invoice_id')
                ->constrained('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('remito_id');
        });
    }
};
