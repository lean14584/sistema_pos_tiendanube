<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Apunta a la factura original que una Nota de Crédito acredita.
            // Null para todo lo demás (Remito/Factura/Devolución).
            $table->foreignId('related_invoice_id')->nullable()->after('client_id')
                ->constrained('invoices')->restrictOnDelete();

            // Si esta fila repone stock al crearse/editarse/borrarse. Default
            // true para que Factura/Remito/Devolución (que no exponen ningún
            // checkbox) sigan funcionando exactamente igual que hoy — solo la
            // pantalla de Nota de Crédito lo deja elegir.
            $table->boolean('afecta_stock')->default(true)->after('tipo_comprobante_interno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_invoice_id');
            $table->dropColumn('afecta_stock');
        });
    }
};
