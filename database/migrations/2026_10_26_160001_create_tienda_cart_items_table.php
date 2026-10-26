<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carrito de un cliente CON cuenta (ver TiendaCart::class) — para que
     * sobreviva al deslogueo/cambio de dispositivo, no puede vivir solo en
     * sesión. Un invitado sin cuenta sigue usando la sesión, como antes.
     */
    public function up(): void
    {
        Schema::create('tienda_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tienda_cart_items');
    }
};
