<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * client_payments no guardaba en qué sucursal se cobró: ReciboPdfController
     * resolvía el punto de venta del recibo con CurrentSucursal::id() de quien
     * lo está viendo/reimprimiendo AHORA, no de la sucursal donde realmente se
     * cobró — reimprimir el mismo recibo desde otra sesión (u otro día, con
     * otra sucursal activa) cambiaba el número. Mismo criterio que
     * add_sucursal_id_to_purchases_table.
     */
    public function up(): void
    {
        Schema::table('client_payments', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('client_id')->constrained('sucursales')->nullOnDelete();
        });

        $sucursalId = DB::table('sucursales')->orderBy('id')->value('id');

        if ($sucursalId !== null) {
            DB::table('client_payments')->whereNull('sucursal_id')->update(['sucursal_id' => $sucursalId]);
        }
    }

    public function down(): void
    {
        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_id');
        });
    }
};
