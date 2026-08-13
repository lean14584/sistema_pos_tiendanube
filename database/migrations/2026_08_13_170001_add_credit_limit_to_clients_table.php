<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Límite de crédito (tope de cuenta corriente) por cliente. Null o 0 =
     * sin límite. Si una venta a cuenta corriente lo superaría, se bloquea.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('credit_limit', 14, 2)->nullable()->after('price_list_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });
    }
};
