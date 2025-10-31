<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // যেমন: "Class 6", "Hifz 1"
            $table->string('code', 50)->nullable();       // ঐচ্ছিক সংক্ষিপ্ত কোড
            $table->unsignedSmallInteger('sort_order')->nullable(); // UI অর্ডারের জন্য
            $table->boolean('is_active')->default(true);  // ডিফল্ট true
            $table->timestamps();

            // Unique: name (tenant-wise, per-DB)
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
