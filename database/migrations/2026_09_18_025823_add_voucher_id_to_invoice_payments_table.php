<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traza qué pago canjeó (parte de) qué vale de cambio. El "method" del
     * pago sigue siendo uno de los valores normales de PaymentMethod (no se
     * agrega un caso "vale" ahí a propósito, para no ofrecerlo como medio de
     * pago en pantallas — Facturas/Compras — que todavía no saben canjearlo);
     * este campo es el que realmente marca que el pago vino de un vale.
     */
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('method')->constrained('vouchers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_id');
        });
    }
};
