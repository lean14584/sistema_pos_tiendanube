<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('barcode_scale_enabled')->default(false);
            $table->string('barcode_scale_prefix')->nullable();
            $table->unsignedTinyInteger('barcode_scale_code_digits')->default(5);
            $table->unsignedTinyInteger('barcode_scale_weight_digits')->default(5);
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['barcode_scale_enabled', 'barcode_scale_prefix', 'barcode_scale_code_digits', 'barcode_scale_weight_digits']);
        });
    }
};
