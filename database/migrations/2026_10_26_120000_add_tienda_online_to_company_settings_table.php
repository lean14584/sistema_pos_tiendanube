<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flags de la tienda online nativa (storefront público, mismo DB que el
     * POS). Apagada por defecto: cada cliente la prende cuando está lista,
     * igual que factura_a_habilitada/tiendanube_store_id. tienda_online_sucursal_id
     * define de qué sucursal lee stock/precio el storefront (mismo criterio
     * que tiendanube_sucursal_id); nullable, con fallback a la primera
     * sucursal si no se eligió ninguna.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('tienda_online_habilitada')->default(false)->after('theme_secondary_color');
            $table->foreignId('tienda_online_sucursal_id')->nullable()->after('tienda_online_habilitada')->constrained('sucursales')->nullOnDelete();
            $table->unsignedInteger('tienda_online_reserva_minutos')->default(15)->after('tienda_online_sucursal_id');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('tienda_online_reserva_minutos');
            $table->dropConstrainedForeignId('tienda_online_sucursal_id');
            $table->dropColumn('tienda_online_habilitada');
        });
    }
};
