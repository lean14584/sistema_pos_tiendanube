<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Impuestos y percepciones de una compra (Percepción IVA, Percepción
     * IIBB, Percepción Ganancias, Impuestos internos, etc.). Se cargan tal
     * como vienen en la factura del proveedor —concepto + monto— y suman al
     * total, sin intentar calcularlos por reglas de AFIP.
     */
    public function up(): void
    {
        Schema::create('purchase_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->string('concepto');
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index('purchase_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_taxes');
    }
};
