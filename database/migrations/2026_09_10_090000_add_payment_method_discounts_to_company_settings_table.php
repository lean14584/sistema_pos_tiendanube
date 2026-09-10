<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->decimal('descuento_efectivo_pct', 5, 2)->default(0);
            $table->decimal('descuento_transferencia_pct', 5, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['descuento_efectivo_pct', 'descuento_transferencia_pct']);
        });
    }
};
