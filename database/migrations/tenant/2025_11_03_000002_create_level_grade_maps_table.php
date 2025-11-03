<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('level_grade_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->nullable(); // grade order within level
            $table->timestamps();

            $table->unique(['level_id', 'grade_id']);
            $table->index(['level_id', 'sort_order']);
            $table->index('grade_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('level_grade_maps');
    }
};
