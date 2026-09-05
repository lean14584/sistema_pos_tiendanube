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
        // invoices.status ya tiene índice desde add_performance_indexes.
        // invoices.cae: Invoice::pendientesDeEmisionCountCached() corre en
        // el sidebar de CADA página del sistema (memoizado solo por request,
        // no entre requests) y hasta ahora hacía table scan completo.
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('cae');
        });

        // products.stock: Product::scopeLowStock() también corre en el
        // sidebar de cada página. products.sku/name: búsquedas de coincidencia
        // en Products\Import y en los buscadores de producto (POS, Compras,
        // etc.), antes sin índice utilizable.
        Schema::table('products', function (Blueprint $table) {
            $table->index('stock');
            $table->index('sku');
            $table->index('name');
        });

        // clients.name: Client::consumidorFinal() busca por nombre exacto en
        // cada venta del POS (la pantalla de mayor tráfico del sistema).
        Schema::table('clients', function (Blueprint $table) {
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['cae']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['stock']);
            $table->dropIndex(['sku']);
            $table->dropIndex(['name']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};
