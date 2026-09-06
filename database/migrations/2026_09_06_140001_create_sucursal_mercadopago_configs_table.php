<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credenciales de Mercado Pago por sucursal (antes era un único
     * MP_ACCESS_TOKEN global en el .env, ver config/mercadopago.php). Tabla
     * separada del ABM de `sucursales` a propósito: el access_token es un
     * secreto y esta tabla NO usa el trait Auditable, así nunca queda en
     * texto plano en el historial de auditoría (a diferencia de `Sucursal`,
     * que sí se audita completa).
     *
     * `collector_id` se completa solo la primera vez que se usa el token
     * (ver MercadoPagoQrService::collectorId) y sirve además para resolver,
     * dado el `user_id` que manda un webhook de MP, a qué sucursal
     * corresponde — sin eso no hay forma de saber con qué token consultar
     * el pago antes de saber de qué sucursal es.
     */
    public function up(): void
    {
        Schema::create('sucursal_mercadopago_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->unique()->constrained('sucursales')->cascadeOnDelete();
            $table->text('access_token')->nullable();
            $table->unsignedBigInteger('collector_id')->nullable();
            $table->string('store_external_id')->nullable();
            $table->string('pos_external_id')->nullable();
            $table->string('store_name')->nullable();
            $table->string('pos_name')->nullable();
            $table->string('store_street')->nullable();
            $table->string('store_number')->nullable();
            $table->string('store_city')->nullable();
            $table->string('store_state')->nullable();
            $table->decimal('store_lat', 10, 7)->nullable();
            $table->decimal('store_lng', 10, 7)->nullable();
            $table->unsignedInteger('category')->nullable();
            $table->string('notification_url')->nullable();
            $table->timestamps();

            $table->index('collector_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal_mercadopago_configs');
    }
};
