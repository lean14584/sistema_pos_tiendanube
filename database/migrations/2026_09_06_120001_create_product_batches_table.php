<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lotes de productos perecederos, con fecha de vencimiento, por sucursal.
     * No reemplaza a product_stocks como fuente de verdad del stock agregado
     * (ver StockAdjuster) — es una capa de trazabilidad aparte: se cargan al
     * recibir mercadería (Purchases\Create) y se dan de baja manualmente
     * desde ProductBatches\Index cuando vencen, generando el StockAdjustment
     * correspondiente. Las ventas NO consumen lotes automáticamente todavía.
     */
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number')->nullable();
            $table->decimal('quantity_received', 12, 2);
            $table->decimal('quantity_remaining', 12, 2);
            $table->date('expiration_date');
            $table->text('notes')->nullable();
            $table->timestamp('written_off_at')->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'expiration_date']);
            $table->index(['product_id', 'sucursal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
