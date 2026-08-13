<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promociones por producto que el POS aplica solo. Tres tipos:
     *  - nxm:      llevás N y pagás M (2x1, 3x2). buy_qty=N, pay_qty=M.
     *  - segunda:  cada 2da unidad con `percent`% de descuento.
     *  - cantidad: desde `min_qty` unidades, `percent`% en toda la línea.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type'); // nxm | segunda | cantidad
            $table->unsignedInteger('buy_qty')->nullable();
            $table->unsignedInteger('pay_qty')->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->unsignedInteger('min_qty')->nullable();
            $table->boolean('active')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
