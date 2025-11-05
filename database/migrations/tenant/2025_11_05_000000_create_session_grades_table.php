<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_grades', function (Blueprint $table) {
            $table->id();

            // FK: academic_sessions.id
            $table->foreignId('academic_session_id')
                ->constrained('academic_sessions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // FK: grades.id
            $table->foreignId('grade_id')
                ->constrained('grades')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Class capacity for this Session+Grade
            $table->unsignedSmallInteger('capacity')->default(0);

            // Optional: class teacher for the whole SessionGrade
            // Assumes tenant 'users' table exists
            $table->foreignId('class_teacher_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Free-form meta (e.g., room info, remarks, custom flags)
            $table->json('meta_json')->nullable();

            $table->timestamps();

            // Prevent duplicate openings of the same Grade within the same Session
            $table->unique(
                ['academic_session_id', 'grade_id'],
                'uniq_session_grades_session_grade'
            );

            // Helpful indexes
            $table->index(['grade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_grades');
    }
};
