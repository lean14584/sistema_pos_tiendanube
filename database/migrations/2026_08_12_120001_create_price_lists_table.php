<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Ajuste porcentual sobre el precio base del producto: 0 = igual,
            // -15 = 15% más barato (mayorista), 10 = 10% más caro (tarjeta).
            $table->decimal('adjustment_percent', 6, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Lista por defecto: "Minorista" al precio base (0%).
        DB::table('price_lists')->insert([
            'name' => 'Minorista',
            'adjustment_percent' => 0,
            'is_default' => true,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
