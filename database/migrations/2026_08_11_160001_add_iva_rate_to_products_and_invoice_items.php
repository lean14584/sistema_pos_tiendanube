<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Alícuota de IVA del producto (0 exento / 10.5 / 21). Default 21,
            // el caso más común.
            $table->decimal('iva_rate', 5, 2)->default(21)->after('price');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            // Nullable a propósito: si un ítem no la tiene cargada, se usa la
            // alícuota de la factura (retrocompatibilidad con comprobantes
            // viejos y con los importados de Tiendanube).
            $table->decimal('iva_rate', 5, 2)->nullable()->after('unit_price');
        });

        // Backfill: los ítems existentes toman la alícuota de su factura, así
        // los totales de los comprobantes ya cargados no cambian.
        DB::statement('UPDATE invoice_items SET iva_rate = (SELECT tax_rate FROM invoices WHERE invoices.id = invoice_items.invoice_id) WHERE iva_rate IS NULL');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('iva_rate');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('iva_rate');
        });
    }
};
