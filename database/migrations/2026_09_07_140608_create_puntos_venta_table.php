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
        Schema::create('puntos_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('nombre')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('numero');
        });

        // Backfill: cada sucursal ya tenía exactamente un punto de venta
        // (columna sucursales.punto_venta, obligatoria) — se convierte en su
        // primer registro de puntos_venta, sin cambiar ningún número.
        DB::table('sucursales')->orderBy('id')->get(['id', 'punto_venta'])->each(function ($sucursal) {
            DB::table('puntos_venta')->insert([
                'sucursal_id' => $sucursal->id,
                'numero' => $sucursal->punto_venta,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropUnique(['punto_venta']);
            $table->dropColumn('punto_venta');
        });

        // El punto de venta de la empresa ya no se usa como fallback: cada
        // sucursal siempre tiene al menos uno propio (ver arriba), y esta
        // columna quedaba redundante y desactualizada apenas se agregaba
        // un segundo punto de venta.
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('punto_venta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->unsignedSmallInteger('punto_venta')->nullable()->after('razon_social');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('punto_venta')->nullable();
        });

        DB::table('puntos_venta')->orderBy('id')->get()->groupBy('sucursal_id')->each(function ($rows, $sucursalId) {
            DB::table('sucursales')->whereKey($sucursalId)->update(['punto_venta' => $rows->first()->numero]);
        });

        Schema::dropIfExists('puntos_venta');
    }
};
