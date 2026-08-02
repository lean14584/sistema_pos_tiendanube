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
        Schema::table('purchases', function (Blueprint $table) {
            // Identificación del comprobante tal como lo emitió el proveedor
            // (distinto de `number`, que es el correlativo interno de esta
            // app). Necesarios para armar el Libro IVA Compras.
            $table->unsignedSmallInteger('tipo_comprobante')->nullable()->after('number');
            $table->unsignedSmallInteger('punto_venta')->nullable()->after('tipo_comprobante');
            $table->unsignedInteger('numero_comprobante')->nullable()->after('punto_venta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['tipo_comprobante', 'punto_venta', 'numero_comprobante']);
        });
    }
};
