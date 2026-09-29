<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos huecos puntuales detectados en una auditoría de N+1/queries:
 * - invoices: Invoices\Index::render() ordena por created_at desc; un admin
 *   global sin filtro de sucursal no puede usar el compuesto
 *   (sucursal_id, status, issue_date) para ese ORDER BY. Mismo criterio ya
 *   aplicado a audit_logs.created_at en add_missing_composite_indexes.
 * - quotes: Quotes\Index::render() ordena por created_at desc sin ningún
 *   índice de soporte (la tabla no tiene sucursal_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
