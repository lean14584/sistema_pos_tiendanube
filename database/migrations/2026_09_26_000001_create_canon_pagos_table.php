<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha_pago');
            $table->string('mp_payment_id')->unique();
            $table->decimal('monto', 10, 2);
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->timestamps();

            $table->unique(['mes', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_pagos');
    }
};
