<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('students', function (Blueprint $table) {
            $table->id();

            $table->string('student_code')->unique(); // ইনস্টিটিউট-স্কোপড ID / ভর্তিনং

            $table->foreignId('user_id')              // tenant users
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /* -------- ব্যক্তিগত তথ্য -------- */
            $table->string('name_bn');                // বাংলা নাম
            $table->string('name_en')->nullable();    // ইংরেজি নাম (optional)
            $table->string('gender', 10)->nullable(); // male | female | other
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
            $table->string('guardian_relation')->nullable(); // Father/Mother/Uncle/Other...

            /* -------- ঠিকানা: JSON -------- */
            $table->json('present_address')->nullable();   // {house, road, village, ...}
            $table->json('permanent_address')->nullable();

            /* -------- আবাসিক স্ট্যাটাস -------- */
            // residential | new_musafir | non_residential
            $table->string('residential_type', 20)->nullable();

            /* -------- স্ট্যাটাস + মেটা -------- */
            // active | inactive | passed | tc_issued | dropped
            $table->string('status', 20)->default('active');

            $table->string('photo_path')->nullable();
            $table->json('meta_json')->nullable();

            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('students');
    }
};
