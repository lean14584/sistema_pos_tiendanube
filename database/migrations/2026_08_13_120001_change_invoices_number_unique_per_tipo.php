<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Con la numeración por punto de venta y tipo (PPPP-NNNNNNNN), dos
     * comprobantes de DISTINTO tipo pueden compartir el mismo número dentro
     * del mismo punto de venta (por ej. Factura B 0001-00000001 y Remito X
     * 0001-00000001), tal como en AFIP. Por eso el número deja de ser único
     * por sí solo y pasa a serlo por (tipo_comprobante_interno, número).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->unique(['tipo_comprobante_interno', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['tipo_comprobante_interno', 'number']);
            $table->unique(['number']);
        });
    }
};
