<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('students', 'document_number') || !Schema::hasColumn('students', 'dni')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->string('student_code')->nullable();
            $table->string('document_number')->nullable();
            $table->string('full_name')->nullable();
            $table->string('modality')->nullable();
            $table->string('faculty')->nullable();
            $table->string('start_semester')->nullable();
            $table->string('start_date')->nullable();
            $table->string('academic_cycle')->nullable();
            $table->string('current_semester')->nullable();
            $table->string('graduation_semester')->nullable();
            $table->string('graduation_date')->nullable();
            $table->string('credits')->nullable();
            $table->string('gender')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('admission_mode')->nullable();
        });

        DB::table('students')->orderBy('id')->each(function ($student) {
            DB::table('students')->where('id', $student->id)->update([
                'document_number' => $student->dni,
                'full_name' => trim(trim((string) $student->surname) . ' ' . trim((string) $student->name)),
                'modality' => $student->program_type,
                'academic_cycle' => $student->period,
                'status' => $student->status ?: 'Activo',
            ]);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['name', 'surname', 'dni', 'program_type', 'period']);
        });

        DB::statement('ALTER TABLE students ALTER COLUMN document_number SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS students_document_number_unique ON students (document_number)');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('students', 'document_number') || Schema::hasColumn('students', 'dni')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('surname')->nullable();
            $table->string('dni')->nullable();
            $table->string('program_type')->nullable();
            $table->string('period')->nullable();
        });

        DB::table('students')->orderBy('id')->each(function ($student) {
            $parts = preg_split('/\s+/', trim((string) $student->full_name), 2);
            DB::table('students')->where('id', $student->id)->update([
                'dni' => $student->document_number,
                'surname' => $parts[0] ?? '',
                'name' => $parts[1] ?? $parts[0] ?? '',
                'program_type' => $student->modality,
                'period' => $student->academic_cycle,
            ]);
        });

        DB::statement('DROP INDEX IF EXISTS students_document_number_unique');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'student_code',
                'document_number',
                'full_name',
                'modality',
                'faculty',
                'start_semester',
                'start_date',
                'academic_cycle',
                'current_semester',
                'graduation_semester',
                'graduation_date',
                'credits',
                'gender',
                'phone',
                'address',
                'admission_mode',
            ]);
        });
    }
};
