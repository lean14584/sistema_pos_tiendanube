<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda, por "forma" de archivo (hash de las cabeceras del Excel), qué
     * columna del Excel corresponde a cada campo del sistema — para no
     * tener que reemparejar cada vez que se sube un archivo con el mismo
     * formato (ej. siempre el mismo proveedor/planilla).
     */
    public function up(): void
    {
        Schema::create('product_import_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('headers_hash')->unique();
            $table->json('headers');
            $table->json('mapping');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_mappings');
    }
};
