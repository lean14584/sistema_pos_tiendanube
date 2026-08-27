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
        Schema::table('invoices', function (Blueprint $table) {
            // Todos estos campos son una fotografía del momento de emisión.
            // Nunca se vuelven a leer de clients/company_settings después:
            // la condición de IVA de un cliente puede cambiar más adelante,
            // pero una factura ya emitida no puede cambiar retroactivamente.
            $table->string('cae', 14)->nullable()->after('status');
            $table->date('cae_vencimiento')->nullable()->after('cae');
            $table->unsignedSmallInteger('tipo_comprobante')->nullable()->after('cae_vencimiento');
            $table->unsignedSmallInteger('punto_venta')->nullable()->after('tipo_comprobante');
            $table->unsignedInteger('numero_comprobante_afip')->nullable()->after('punto_venta');
            $table->unsignedTinyInteger('condicion_iva_receptor_id')->nullable()->after('numero_comprobante_afip');
            $table->string('condicion_iva_emisor')->nullable()->after('condicion_iva_receptor_id');
            $table->text('afip_observaciones')->nullable()->after('condicion_iva_emisor');
            $table->json('afip_response')->nullable()->after('afip_observaciones');
            $table->timestamp('emitted_at')->nullable()->after('afip_response');

            // Nombre explícito y corto: el autogenerado por Laravel supera los
            // 64 caracteres que soporta MySQL como nombre de índice (en SQLite
            // no hay ese límite, por eso no se notaba).
            $table->unique(['punto_venta', 'tipo_comprobante', 'numero_comprobante_afip'], 'invoices_pv_tipo_numero_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_pv_tipo_numero_unique');
            $table->dropColumn([
                'cae', 'cae_vencimiento', 'tipo_comprobante', 'punto_venta',
                'numero_comprobante_afip', 'condicion_iva_receptor_id',
                'condicion_iva_emisor', 'afip_observaciones', 'afip_response', 'emitted_at',
            ]);
        });
    }
};
