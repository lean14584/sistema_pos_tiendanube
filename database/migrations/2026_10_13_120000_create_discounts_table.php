<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de descuentos manuales ("Mis Descuentos") que el cajero puede
     * elegir en el POS para una venta puntual (ej. "Día de la Madre -15%"),
     * a diferencia de Promotion (atada a un producto, la aplica el sistema
     * solo). Dos tipos:
     *  - percent: `value` es un % (0-100) sobre el total de la venta.
     *  - fixed:   `value` son pesos fijos a descontar del total.
     */
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // percent | fixed
            $table->decimal('value', 12, 2);
            $table->boolean('active')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
