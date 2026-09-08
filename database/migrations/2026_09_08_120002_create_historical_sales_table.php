<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ventas importadas desde otro sistema (ej. Tango) para consulta e
     * informes. Deliberadamente NO son `Invoice`: no llevan numeración
     * AFIP/CAE, no entran al Libro IVA, y no afectan el saldo de cuenta
     * corriente del cliente (eso se carga aparte como `opening_balance`).
     */
    public function up(): void
    {
        Schema::create('historical_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            // Nombre del cliente tal como venía en el Excel, para las filas
            // que no matchearon ningún cliente existente (o si el cliente
            // se borra después: el registro histórico no debe desaparecer).
            $table->string('client_name_raw');
            $table->date('sale_date');
            $table->string('comprobante_type')->nullable();
            $table->string('comprobante_number')->nullable();
            $table->decimal('total', 14, 2);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'sale_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_sales');
    }
};
