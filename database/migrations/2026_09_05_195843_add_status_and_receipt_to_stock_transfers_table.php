<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El envío pasa a ser en dos pasos: se descuenta del origen al crearlo,
     * y recién se acredita en el destino cuando alguien ahí confirma qué
     * recibió (puede diferir de lo enviado por rotura/pérdida en el
     * traslado). Antes de esto, las dos partes se movían juntas al crear el
     * envío — ver StockTransfers\Index.
     */
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('status')->default('pendiente')->after('to_sucursal_id');
            $table->timestamp('received_at')->nullable()->after('notes');
            $table->foreignId('received_by_user_id')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
        });

        // Los envíos de antes de esta migración ya movieron las dos partes
        // atómicamente al crearse (no había paso de confirmación todavía):
        // quedan marcados como recibidos desde su propia fecha de creación,
        // para no aparecer como "pendientes" eternos ni volver a acreditar
        // el destino si alguien los confirmara ahora.
        DB::table('stock_transfers')->update([
            'status' => 'recibido',
            'received_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by_user_id');
            $table->dropColumn(['status', 'received_at']);
        });
    }
};
