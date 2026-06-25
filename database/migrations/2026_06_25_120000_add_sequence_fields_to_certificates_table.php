<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('sequence_suffix', 10)->nullable()->after('code');
            $table->unsignedInteger('sequence_start')->default(1)->after('sequence_suffix');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['sequence_suffix', 'sequence_start']);
        });
    }
};
