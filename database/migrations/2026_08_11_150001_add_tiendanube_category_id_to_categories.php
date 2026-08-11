<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'tiendanube_category_id')) {
                // Vincula la categoría local con la de Tiendanube para no
                // duplicarla al importar productos.
                $table->unsignedBigInteger('tiendanube_category_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'tiendanube_category_id')) {
                $table->dropColumn('tiendanube_category_id');
            }
        });
    }
};
