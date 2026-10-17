<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Null = usa la paleta indigo/sky de fábrica tal cual viene en
            // app.css, sin ninguna inyección de CSS extra — así una instalación
            // existente que nunca toca esto no cambia un solo pixel.
            if (! Schema::hasColumn('company_settings', 'theme_primary_color')) {
                $table->string('theme_primary_color', 7)->nullable();
            }
            if (! Schema::hasColumn('company_settings', 'theme_secondary_color')) {
                $table->string('theme_secondary_color', 7)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'theme_primary_color')) {
                $table->dropColumn('theme_primary_color');
            }
            if (Schema::hasColumn('company_settings', 'theme_secondary_color')) {
                $table->dropColumn('theme_secondary_color');
            }
        });
    }
};
