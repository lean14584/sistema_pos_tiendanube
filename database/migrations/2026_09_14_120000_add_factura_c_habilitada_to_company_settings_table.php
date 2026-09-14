<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'factura_c_habilitada')) {
                // Default false: a diferencia de A/B (que ya se ofrecían),
                // Factura C es un tipo nuevo — ninguna instalación existente
                // debe empezar a ofrecerla sin que alguien la prenda a mano
                // (y solo tiene sentido para una empresa Monotributista/Exenta).
                $table->boolean('factura_c_habilitada')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'factura_c_habilitada')) {
                $table->dropColumn('factura_c_habilitada');
            }
        });
    }
};
