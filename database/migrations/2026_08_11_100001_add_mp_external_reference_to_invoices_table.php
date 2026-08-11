<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'mp_external_reference')) {
                // Referencia única que se manda a Mercado Pago al empujar el
                // monto a la caja. Permite retomar el polling / procesar el
                // webhook aunque se recargue la página.
                $table->string('mp_external_reference')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'mp_external_reference')) {
                $table->dropColumn('mp_external_reference');
            }
        });
    }
};
