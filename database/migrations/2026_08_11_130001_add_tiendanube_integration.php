<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'tiendanube_store_id')) {
                $table->string('tiendanube_store_id')->nullable();
            }
            if (! Schema::hasColumn('company_settings', 'tiendanube_token')) {
                $table->string('tiendanube_token')->nullable();
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'tiendanube_product_id')) {
                // Vincula el producto local con el de Tiendanube para no
                // duplicar al re-importar y para poder sincronizar stock.
                $table->unsignedBigInteger('tiendanube_product_id')->nullable()->index();
            }
            if (! Schema::hasColumn('products', 'tiendanube_variant_id')) {
                $table->unsignedBigInteger('tiendanube_variant_id')->nullable();
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'tiendanube_order_id')) {
                // Evita crear dos veces la factura del mismo pedido online.
                $table->unsignedBigInteger('tiendanube_order_id')->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            foreach (['tiendanube_store_id', 'tiendanube_token'] as $col) {
                if (Schema::hasColumn('company_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            foreach (['tiendanube_product_id', 'tiendanube_variant_id'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'tiendanube_order_id')) {
                $table->dropColumn('tiendanube_order_id');
            }
        });
    }
};
