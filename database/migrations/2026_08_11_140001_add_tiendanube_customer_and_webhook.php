<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'tiendanube_customer_id')) {
                $table->unsignedBigInteger('tiendanube_customer_id')->nullable()->index();
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'tiendanube_webhook_secret')) {
                // Client secret de la app: Tiendanube firma los webhooks con
                // esto (HMAC). Si está cargado, se valida la firma.
                $table->string('tiendanube_webhook_secret')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'tiendanube_customer_id')) {
                $table->dropColumn('tiendanube_customer_id');
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'tiendanube_webhook_secret')) {
                $table->dropColumn('tiendanube_webhook_secret');
            }
        });
    }
};
