<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable a nivel de base a propósito: un admin es global (no
            // pertenece a ninguna sucursal en particular) y los usuarios
            // existentes antes de esta migración quedan sin romper. Que un
            // cajero/vendedor deba tener una sucursal se exige en la
            // validación del formulario (Users\Create/Edit), no acá.
            $table->foreignId('sucursal_id')->nullable()->after('role')->constrained('sucursales')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_id');
        });
    }
};
