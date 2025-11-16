<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('session_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')
                ->constrained('academic_sessions')
                ->cascadeOnDelete();
            $table->foreignId('grade_id')
                ->constrained('grades')
                ->cascadeOnDelete();
            $table->foreignId('fee_id')
                ->constrained('fees')
                ->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            $table->unique(
                ['academic_session_id', 'grade_id', 'fee_id'],
                'uniq_session_grade_fee'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_fees');
    }
};