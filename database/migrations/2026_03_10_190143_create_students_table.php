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

            // Identidad
            $table->string('student_code')->unique()->comment('Columna 35 o 8');
            $table->string('document_number')->unique()->comment('Columna 5');
            $table->string('full_name')->comment('Columna 2');
            $table->string('gender', 1)->nullable()->comment('Columna 3 (M/F)');

            // Contacto
            $table->string('email')->nullable()->comment('Columna 11');
            $table->string('phone')->nullable()->comment('Columna 10');
            $table->string('address')->nullable()->comment('Columna 6');

            // Académico (Variables fuertes para las constancias)
            $table->string('admission_mode')->nullable()->comment('Columna 1');
            $table->string('program')->nullable()->comment('Columna 20');
            $table->string('campus')->nullable()->comment('Columna 23');
            $table->string('modality')->nullable()->comment('Columna 24');
            $table->string('shift')->nullable()->comment('Columna 25 (Turno)');
            $table->string('status')->nullable()->comment('Columna 26 (Situacion)');
            $table->string('graduation_year')->nullable()->comment('Columna 37');
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
