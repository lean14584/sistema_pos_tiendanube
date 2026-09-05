<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('razon_social');
            $table->string('logo_path')->nullable();
            // Punto de venta AFIP: mismo CUIT que la empresa, un punto de
            // venta habilitado por sucursal (ver CompanySettings::cuit).
            $table->unsignedInteger('punto_venta')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
