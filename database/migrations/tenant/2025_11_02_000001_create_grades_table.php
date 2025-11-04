<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();

            // NEW: level reference
            $table->foreignId('level_id')
                ->constrained('levels')       // references id on levels
                ->cascadeOnUpdate()
                ->restrictOnDelete();         // prevent deleting level if grades exist

            $table->string('name');                       // e.g., "Class 6", "Hifz 1"
            $table->string('code', 50)->nullable();       // optional short code
            $table->unsignedSmallInteger('sort_order')->nullable(); // UI ordering
            $table->boolean('is_active')->default(true);  // default true
            $table->timestamps();

            $table->unique('name');                       // keep as-is (global unique)
            $table->index(['level_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
