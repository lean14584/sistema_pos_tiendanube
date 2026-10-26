<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A qué cuenta pertenece este pedido, si el cliente estaba logueado al
     * hacer checkout (nullable: un invitado también puede comprar, ver
     * Checkout::confirmar) — permite listar "Mis pedidos" sin depender del
     * email como clave débil.
     */
    public function up(): void
    {
        Schema::table('tienda_pedidos', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('token')->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tienda_pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
