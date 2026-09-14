<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * products.sku no tenía ninguna restricción de unicidad: se podían
     * cargar dos productos con el mismo código (alta manual, import de
     * Excel, o una carrera al sugerir el mismo siguiente código libre). El
     * escaneo de código de barras (Pos\Index::addByBarcode(), Ajustes de
     * Stock, Etiquetas, Compras) resuelve con Product::where('sku', $code)
     * ->first(), así que ante un duplicado siempre devolvía el mismo
     * producto (el de menor id) sin importar cuál se haya querido escanear.
     */
    public function up(): void
    {
        // Antes de exigir unicidad, resolver los duplicados que ya existan:
        // se le agrega un sufijo al sku de cada copia extra (se deja el de
        // menor id, el "original", tal cual está) — no se puede simplemente
        // vaciarlo, porque un sku vacío rompería el escaneo de ese producto.
        $duplicados = DB::table('products')
            ->select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('sku');

        foreach ($duplicados as $sku) {
            $ids = DB::table('products')->where('sku', $sku)->orderBy('id')->pluck('id');

            foreach ($ids->skip(1)->values() as $i => $id) {
                DB::table('products')->where('id', $id)->update(['sku' => $sku.'-dup'.($i + 1)]);
            }
        }

        Schema::table('products', function (Blueprint $table) {
            // Reemplaza el índice simple (no único) agregado en
            // add_missing_performance_indexes por uno único — sigue
            // sirviendo igual para las búsquedas de coincidencia.
            $table->dropIndex(['sku']);
            $table->unique('sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->index('sku');
        });
    }
};
