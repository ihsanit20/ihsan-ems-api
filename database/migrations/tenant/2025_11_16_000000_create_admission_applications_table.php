<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('admission_applications', function (Blueprint $table) {
            $table->id();

            $table->string('application_no')->unique();

            $table->foreignId('academic_session_id')
                ->constrained('academic_sessions')
                ->cascadeOnDelete();

            $table->foreignId('session_grade_id')
                ->constrained('session_grades')
                ->cascadeOnDelete();

            // new | re_admission
            $table->string('application_type', 20)->default('new');

            // পুরাতন স্টুডেন্ট হলে
            $table->foreignId('existing_student_id')
                ->nullable()
                ->constrained('students')
                ->nullOnDelete();

            /* -------- ছাত্রের বেসিক তথ্য -------- */
            $table->string('applicant_name');
            $table->string('gender', 10)->nullable();         // male | female | other
            $table->date('date_of_birth')->nullable();
            $table->string('student_phone', 20)->nullable();
            $table->string('student_email')->nullable();

            /* -------- পিতা/মাতা তথ্য -------- */
            $table->string('father_name')->nullable();
            $table->string('father_phone', 20)->nullable();
            $table->string('father_occupation')->nullable();

            $table->string('mother_name')->nullable();
            $table->string('mother_phone', 20)->nullable();
            $table->string('mother_occupation')->nullable();

            /* -------- অভিভাবক তথ্য -------- */
            // father | mother | other
            $table->string('guardian_type', 20)->default('father');

            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 20)->nullable();
            $table->string('guardian_relation')->nullable();  // Father/Mother/Uncle/Other...

            /* -------- ঠিকানা: JSON -------- */
            $table->json('present_address')->nullable();   // {house, road, village, ...}
            $table->json('permanent_address')->nullable();
            $table->boolean('is_present_same_as_permanent')->default(false);

            /* -------- আগের শিক্ষা প্রতিষ্ঠান -------- */
            $table->string('previous_institution_name')->nullable();
            $table->string('previous_class')->nullable();
            $table->string('previous_result')->nullable();
            $table->string('previous_result_division')->nullable();

            /* -------- আবাসিক স্ট্যাটাস -------- */
            // residential | new_musafir | non_residential
            $table->string('residential_type', 20)->nullable();

            /* -------- আবেদন মেটা -------- */
            // online | offline
            $table->string('applied_via', 20)->nullable();
            $table->date('application_date')->nullable();

            /* -------- স্ট্যাটাস -------- */
            // pending | accepted | rejected | admitted
            $table->string('status', 20)->default('pending');
            $table->text('status_note')->nullable();

            // ভর্তি সম্পন্ন হলে যে student তৈরি হবে
            $table->foreignId('admitted_student_id')
                ->nullable()
                ->constrained('students')
                ->nullOnDelete();

            $table->string('photo_path')->nullable();
            $table->json('meta_json')->nullable();

            $table->timestamps();

            $table->index(['academic_session_id', 'session_grade_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('admission_applications');
    }
};
