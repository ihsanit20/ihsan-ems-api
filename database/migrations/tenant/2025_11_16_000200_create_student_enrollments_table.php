<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('student_enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignId('academic_session_id')
                ->constrained('academic_sessions')
                ->cascadeOnDelete();

            $table->foreignId('session_grade_id')
                ->constrained('session_grades')
                ->cascadeOnDelete();

            $table->foreignId('section_id')
                ->nullable()
                ->constrained('sections')
                ->nullOnDelete();

            $table->string('roll_no', 50)->nullable();

            // new | promotion | re_admission | transfer_in
            $table->string('admission_type', 20)->default('new');

            $table->foreignId('application_id')
                ->nullable()
                ->constrained('admission_applications')
                ->nullOnDelete();

            $table->date('admission_date')->nullable();

            // active | promoted | passed | tc_issued | dropped
            $table->string('status', 20)->default('active');

            $table->string('remarks')->nullable();

            $table->timestamps();

            $table->unique(
                ['academic_session_id', 'student_id'],
                'uniq_session_student'
            );

            $table->index(['academic_session_id', 'session_grade_id'], 'idx_session_class');
            $table->index(['status'], 'idx_enrollment_status');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('student_enrollments');
    }
};