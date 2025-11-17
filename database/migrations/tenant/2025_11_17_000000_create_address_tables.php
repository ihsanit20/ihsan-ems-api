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
        // Create divisions table
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('en_name')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Create districts table
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('en_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Create areas table
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->nullable()->constrained('districts')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('en_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('areas');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('divisions');
    }
};
