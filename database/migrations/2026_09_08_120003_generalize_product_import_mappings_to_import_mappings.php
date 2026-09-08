<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El "recordar mapeo de columnas de Excel" ya no es solo para
     * Productos: Clientes, Proveedores, Ventas históricas y los saldos de
     * apertura lo reusan. Se generaliza agregando `context` (qué pantalla
     * de import es) para que dos imports distintos con cabeceras iguales
     * no choquen entre sí.
     */
    public function up(): void
    {
        Schema::rename('product_import_mappings', 'import_mappings');

        Schema::table('import_mappings', function (Blueprint $table) {
            $table->string('context')->default('products')->after('id');
        });

        Schema::table('import_mappings', function (Blueprint $table) {
            $table->dropUnique('product_import_mappings_headers_hash_unique');
            $table->unique(['context', 'headers_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('import_mappings', function (Blueprint $table) {
            $table->dropUnique(['context', 'headers_hash']);
        });

        Schema::table('import_mappings', function (Blueprint $table) {
            $table->dropColumn('context');
            $table->unique('headers_hash', 'product_import_mappings_headers_hash_unique');
        });

        Schema::rename('import_mappings', 'product_import_mappings');
    }
};
