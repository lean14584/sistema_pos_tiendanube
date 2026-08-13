<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promociones por FAMILIA/grupo de productos: NxM cruzando varios
     * productos (ej. Coca/Fanta/Sprite, 3x2). El POS cuenta las unidades de
     * todo el grupo y regala las MÁS BARATAS.
     */
    public function up(): void
    {
        Schema::create('promotion_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('buy_qty'); // N (llevás)
            $table->unsignedInteger('pay_qty'); // M (pagás)
            $table->boolean('active')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('promotion_group_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_group_id')->constrained('promotion_groups')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unique(['promotion_group_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_group_product');
        Schema::dropIfExists('promotion_groups');
    }
};
