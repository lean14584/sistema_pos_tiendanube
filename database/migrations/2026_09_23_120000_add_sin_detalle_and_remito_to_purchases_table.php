<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->boolean('sin_detalle')->default(false)->after('status');
            $table->decimal('manual_total', 12, 2)->nullable()->after('sin_detalle');
            $table->string('remito_number', 60)->nullable()->after('manual_total');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['sin_detalle', 'manual_total', 'remito_number']);
        });
    }
};
