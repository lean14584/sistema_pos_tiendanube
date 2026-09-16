<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos huecos que se detectaron auditando índices contra el patrón que ya
 * se usó en 2026_09_15_120000_add_missing_composite_indexes: mismo filtro
 * (sucursal_id) + mismo orden (created_at desc) que purchases/cash_sessions,
 * pero invoices y stock_adjustments no lo recibieron en esa pasada - parece
 * un olvido, no una omisión a propósito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Invoices\Index: filtro por sucursal_id + orden por created_at
            // desc (el listado principal de facturas, paginado, alto
            // tráfico). El compuesto (sucursal_id, status, issue_date) que
            // ya existe sirve para reportes por rango de fecha, no para
            // este ORDER BY created_at.
            $table->index(['sucursal_id', 'created_at']);
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            // StockAdjustments\Index: filtro por sucursal_id + orden por
            // created_at desc (mismo patrón recién arreglado en el propio
            // render() para que filtre por sucursal - ver commit de la
            // auditoría de seguridad).
            $table->index(['sucursal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['sucursal_id', 'created_at']);
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropIndex(['sucursal_id', 'created_at']);
        });
    }
};
