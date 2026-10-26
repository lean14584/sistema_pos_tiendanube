<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cabecera pública del pedido web: token en vez del PK de Invoice para
     * las URLs públicas (/tienda/pedido/{token}), y los datos de contacto
     * del invitado (no hay login de cliente, ver plan de la tienda online).
     * invoice_id apunta al Remito creado al momento de la reserva de stock
     * (ver Checkout::confirmar) — nullable solo por si algún día se separa
     * la creación del pedido de la reserva, hoy siempre se crean juntos.
     */
    public function up(): void
    {
        Schema::create('tienda_pedidos', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('invoice_id')->nullable()->unique()->constrained('invoices')->nullOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->string('status')->default('pending_payment');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->json('cart_snapshot');
            $table->string('mp_preference_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tienda_pedidos');
    }
};
