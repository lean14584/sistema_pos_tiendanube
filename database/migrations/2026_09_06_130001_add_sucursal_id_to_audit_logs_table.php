<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sucursal desde la que se hizo la acción auditada (la del usuario en
     * ese momento, ver Auditable::writeAuditLog) — no la del modelo
     * auditado, que puede no tener sucursal propia (ej. Product, Client).
     * Permite que un Encargado filtre "la auditoría de mi sucursal" sin
     * depender de que cada modelo auditado tenga su propia columna.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('user_id')->constrained('sucursales')->nullOnDelete();

            $table->index(['sucursal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['sucursal_id', 'created_at']);
            $table->dropConstrainedForeignId('sucursal_id');
        });
    }
};
