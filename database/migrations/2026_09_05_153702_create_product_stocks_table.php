<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->integer('stock')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'sucursal_id']);
        });

        $this->backfill();
    }

    /**
     * `products.stock` era el único stock del sistema (una sola sucursal
     * implícita). A partir de esta migración pasa a ser un agregado
     * mantenido por StockAdjuster (suma de product_stocks), y el stock real
     * por sucursal vive acá. Sin esto, un sistema recién migrado con
     * productos ya cargados se queda sin ningún product_stocks y el POS no
     * podría vender nada — hay que preservar el stock existente en algún
     * lado.
     *
     * Si todavía no existe ninguna sucursal (lo más probable: esta
     * migración corre antes de que el dueño haya dado de alta ninguna desde
     * el ABM), se crea una "Principal" a partir de los datos de la empresa
     * para no dejar el sistema sin poder operar. Si ya hay una o más
     * sucursales, se usa la más vieja (menor id) — no hay forma de saber
     * automáticamente en qué local físico está reamente el stock cargado
     * hasta ahora; el dueño puede redistribuirlo después con Ajuste de Stock.
     */
    private function backfill(): void
    {
        $sucursalId = DB::table('sucursales')->orderBy('id')->value('id');

        if ($sucursalId === null) {
            $company = DB::table('company_settings')->find(1);

            $sucursalId = DB::table('sucursales')->insertGetId([
                'name' => 'Principal',
                'razon_social' => $company->razon_social ?? 'Mi Empresa',
                'punto_venta' => $company->punto_venta ?? 1,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $now = now();

        DB::table('products')
            ->select('id', 'stock')
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($sucursalId, $now) {
                $rows = $products->map(fn ($product) => [
                    'product_id' => $product->id,
                    'sucursal_id' => $sucursalId,
                    'stock' => $product->stock,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows !== []) {
                    DB::table('product_stocks')->insert($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }
};
