<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('session_grade_id')->constrained('session_grades')->cascadeOnDelete();

            $t->string('name');     // e.g., A/B/ح
            $t->string('code')->nullable();
            $t->unsignedSmallInteger('capacity')->nullable();

            $t->foreignId('class_teacher_id')->nullable()->constrained('employees')->nullOnDelete();

            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();

            $t->unique(['session_grade_id', 'name'], 'uniq_section_name_per_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};