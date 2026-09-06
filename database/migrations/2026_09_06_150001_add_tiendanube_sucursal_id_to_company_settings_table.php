<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Con qué sucursal se sincroniza el stock de Tiendanube. Antes de
     * multisucursal, pushStock/pullStock escribían directo en
     * `products.stock` (entonces la única fuente de verdad); ahora ese
     * campo es un AGREGADO mantenido por StockAdjuster (suma de
     * product_stocks), así que Tiendanube necesita saber a qué sucursal
     * concreta corresponde su stock online para no romper esa cuenta (ver
     * TiendanubeSync). Nullable: si no se elige ninguna, se usa la primera
     * sucursal como red de seguridad (mismo criterio que CurrentSucursal).
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->foreignId('tiendanube_sucursal_id')->nullable()->after('tiendanube_webhook_secret')->constrained('sucursales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tiendanube_sucursal_id');
        });
    }
};
