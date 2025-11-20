<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('session_subjects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('session_id')
                ->constrained('academic_sessions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->smallInteger('sort_order')->nullable();
            $table->string('book_name')->nullable();

            $table->timestamps();

            $table->unique(['session_id', 'subject_id']);

            $table->index(['session_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_sessions');
    }
};
