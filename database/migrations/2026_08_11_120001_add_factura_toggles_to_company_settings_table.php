<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'factura_a_habilitada')) {
                // Default true para no cambiar el comportamiento actual (hoy
                // se ofrecen A y B siempre). El usuario puede apagar la que no use.
                $table->boolean('factura_a_habilitada')->default(true);
            }
            if (! Schema::hasColumn('company_settings', 'factura_b_habilitada')) {
                $table->boolean('factura_b_habilitada')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            foreach (['factura_a_habilitada', 'factura_b_habilitada'] as $col) {
                if (Schema::hasColumn('company_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
