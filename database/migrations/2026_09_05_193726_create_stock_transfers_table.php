<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Envío de mercadería entre sucursales: resta el stock de una y suma el
     * de la otra como una sola operación (ver StockTransfers\Index). Es un
     * registro inmutable, como stock_adjustments: no tiene updated_at ni se
     * edita después de creado.
     */
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('to_sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['from_sucursal_id', 'created_at']);
            $table->index(['to_sucursal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
