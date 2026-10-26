<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuenta de cliente de la tienda online — guard `customer` propio,
     * separado de `users` (staff) y de `clients` (ficha de facturación/CRM,
     * no autenticable). Reusa la misma tabla `password_reset_tokens` que ya
     * usa el staff para el reset de contraseña (ver config/auth.php).
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
