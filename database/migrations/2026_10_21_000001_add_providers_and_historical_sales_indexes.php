<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos huecos puntuales detectados en un repaso de performance:
 * - providers: Providers\Index::render() ordena por name sin filtro, y
 *   Purchases\Index::render() busca por whereHas('provider', name like) —
 *   mismo patrón ya indexado en clients.name, pero providers.name había
 *   quedado afuera de esa pasada.
 * - historical_sales: HistoricalSales\Index::render() ordena por sale_date
 *   desc, pero el buscador filtra por client_name_raw (texto libre del
 *   Excel importado), no por client_id — el índice compuesto existente
 *   (client_id, sale_date) no es usable como índice de orden sin una
 *   condición de igualdad sobre client_id primero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('historical_sales', function (Blueprint $table) {
            $table->index('sale_date');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('historical_sales', function (Blueprint $table) {
            $table->dropIndex(['sale_date']);
        });
    }
};
