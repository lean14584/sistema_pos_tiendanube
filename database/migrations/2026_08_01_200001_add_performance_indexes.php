<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite (el driver de esta app) no indexa automáticamente las columnas de
 * foreignId()->constrained() como sí hace MySQL con InnoDB — verificado con
 * PRAGMA index_list() contra la base real: ni invoice_items, ni
 * purchase_items, ni quote_items, ni messages, ni tasks tenían un solo
 * índice antes de esta migración. Cada join o filtro por esas columnas
 * hacía table scan completo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('client_id');
            $table->index('status');
            $table->index('issue_date');
            $table->index('related_invoice_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->index('invoice_id');
            $table->index('product_id');
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->index('invoice_id');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index('provider_id');
            $table->index('status');
            $table->index('issue_date');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->index('purchase_id');
            $table->index('product_id');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->index('client_id');
            $table->index('status');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->index('quote_id');
            $table->index('product_id');
        });

        Schema::table('client_payments', function (Blueprint $table) {
            $table->index('client_id');
        });

        Schema::table('provider_payments', function (Blueprint $table) {
            $table->index('provider_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index('sender_id');
            // Consultado en cada request por Message::unreadFor() (badge del sidebar).
            $table->index(['recipient_id', 'read_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('assigned_by');
            // Consultado en cada request por el badge de tareas abiertas del sidebar.
            $table->index(['status', 'assigned_to']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['issue_date']);
            $table->dropIndex(['related_invoice_id']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['product_id']);
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['provider_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['issue_date']);
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropIndex(['purchase_id']);
            $table->dropIndex(['product_id']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropIndex(['quote_id']);
            $table->dropIndex(['product_id']);
        });

        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
        });

        Schema::table('provider_payments', function (Blueprint $table) {
            $table->dropIndex(['provider_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['sender_id']);
            $table->dropIndex(['recipient_id', 'read_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['assigned_by']);
            $table->dropIndex(['status', 'assigned_to']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
        });
    }
};
