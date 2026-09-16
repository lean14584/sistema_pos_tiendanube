<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Secret Key que Mercado Pago muestra al crear la suscripción al webhook
     * en su panel de desarrolladores — sirve para validar la firma
     * (header x-signature) de cada notificación que llega a /mp/webhook, que
     * hoy es una ruta pública sin ninguna verificación (ver
     * MercadoPagoWebhookController). Es un secreto propio de esa suscripción
     * de webhook (no el mismo que el access_token), por eso no reutiliza esa
     * columna. Igual que access_token, esta tabla no usa Auditable.
     */
    public function up(): void
    {
        Schema::table('sucursal_mercadopago_configs', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('access_token');
        });
    }

    public function down(): void
    {
        Schema::table('sucursal_mercadopago_configs', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
};
