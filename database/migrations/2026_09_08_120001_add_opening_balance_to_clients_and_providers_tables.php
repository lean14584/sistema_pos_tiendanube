<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saldo de apertura para migrar cuentas corrientes desde otro sistema
     * (ej. Tango): un punto de partida fijo que se suma al cálculo en vivo
     * de saldoCuentaCorriente(), en vez de tener que reconstruir cada
     * comprobante histórico como Invoice/Purchase.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('opening_balance', 14, 2)->nullable()->after('credit_limit');
            $table->date('opening_balance_date')->nullable()->after('opening_balance');
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->decimal('opening_balance', 14, 2)->nullable()->after('tipo_documento');
            $table->date('opening_balance_date')->nullable()->after('opening_balance');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'opening_balance_date']);
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'opening_balance_date']);
        });
    }
};
