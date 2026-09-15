<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices que faltaban para los filtros que realmente se usan en pantallas
 * de alto tráfico. Hoy no se nota (tablas chicas) pero sin esto MySQL va a
 * empezar a hacer filesort/scan completo a medida que crece el histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Ya existe (sucursal_id, created_at), pero un admin global sin
            // filtro de sucursal (el caso por defecto en Audit\Index) no lo
            // puede usar para el ORDER BY created_at DESC.
            $table->index('created_at');
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            // openSessionModel() / otherOpenSessions(): sucursal_id + status + user_id.
            $table->index(['sucursal_id', 'status', 'user_id']);
            // closedSessions(): sucursal_id + status, ordenado por closed_at DESC.
            $table->index(['sucursal_id', 'status', 'closed_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Patrón repetido en Reports/Dashboard/Vencimientos: filtrar por
            // sucursal + status y acotar/ordenar por fecha.
            $table->index(['sucursal_id', 'status', 'issue_date']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            // Purchases\Index: filtro por sucursal_id + orden por created_at desc.
            $table->index(['sucursal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropIndex(['sucursal_id', 'status', 'user_id']);
            $table->dropIndex(['sucursal_id', 'status', 'closed_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['sucursal_id', 'status', 'issue_date']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['sucursal_id', 'created_at']);
        });
    }
};
