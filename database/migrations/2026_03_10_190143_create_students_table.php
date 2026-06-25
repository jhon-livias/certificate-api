<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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

            $table->string('student_code')->nullable();
            $table->string('document_number');
            $table->string('full_name');
            $table->string('program')->nullable();
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
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('admission_mode')->nullable();
            $table->string('status')->nullable();

            $table->unique('document_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
