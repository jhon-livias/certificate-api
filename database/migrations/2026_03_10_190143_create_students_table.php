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
            $table->string('full_name')->comment('Columna A: FULL_NAME');
            $table->string('document_number')->unique()->comment('Columna B: DNI');
            $table->string('student_code')->unique()->comment('Columna C: STUDENT_CODE');

            // Información Académica Base
            $table->string('program')->nullable()->comment('Columna D: PROGRAM');
            $table->string('modality')->nullable()->comment('Columna E: MODALITY');
            $table->string('faculty')->nullable()->comment('Columna H: FACULTY');

            // Fechas y Semestres
            $table->string('start_semester')->nullable()->comment('Columna F: START_SEMESTER');
            $table->date('start_date')->nullable()->comment('Columna G: START_DATE'); // Tipo DATE
            $table->string('academic_cycle')->nullable()->comment('Columna L: ACADEMIC_CYCLE');
            $table->string('current_semester')->nullable()->comment('Columna M: CURRENT_SEMESTER');

            // Egreso y Notas
            $table->string('graduation_semester')->nullable()->comment('Columna I: GRADUATION_SEMESTER');
            $table->date('graduation_date')->nullable()->comment('Columna J: GRADUATION_DATE'); // Tipo DATE
            $table->integer('credits')->nullable()->comment('Columna K: CREDITS'); // Tipo INT

            // Campos extra (Los mantenemos nullables por si el SGA los manda por API en el futuro)
            $table->string('gender', 1)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('admission_mode')->nullable();
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
