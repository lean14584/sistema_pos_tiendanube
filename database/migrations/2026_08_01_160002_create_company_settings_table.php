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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('cuit')->default('');
            $table->string('razon_social')->default('');
            $table->string('nombre_fantasia')->nullable();
            $table->string('domicilio')->nullable();
            $table->unsignedSmallInteger('punto_venta')->default(1);
            $table->string('condicion_iva')->default('responsable_inscripto');
            $table->timestamps();
        });

        // Fila única: el resto de la app la referencia siempre por id=1
        // (CompanySettings::current()) para no tener que hacer firstOrCreate
        // en cada request ni lidiar con una tabla vacía.
        DB::table('company_settings')->insert([
            'cuit' => '',
            'razon_social' => '',
            'punto_venta' => 1,
            'condicion_iva' => 'responsable_inscripto',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
