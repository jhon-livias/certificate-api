<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->datetime('creation_time')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->foreignId('creator_user_id')->nullable()->constrained('users');
            $table->datetime('last_modification_time')->nullable();
            $table->foreignId('last_modifier_user_id')->nullable()->constrained('users');
            $table->datetime('deletion_time')->nullable();
            $table->foreignId('deleter_user_id')->nullable()->constrained('users');
            $table->boolean('is_deleted')->default(false);

            // Identidad (Las 3 primeras columnas del Excel)
            $table->string('name')->comment('Columna A: NOMBRE');
            $table->string('surname')->comment('Columna B: APELLIDO');
            $table->string('dni')->comment('Columna C: DNI');
            $table->string('program_type')->comment('Columna D: TIPO DE PROGRAMA');
            $table->string('program')->comment('Columna E: PROGRAMA');
            $table->string('period')->comment('Columna F: PERIODO');
            $table->string('email')->comment('Columna G: CORREO');
            $table->string('status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
