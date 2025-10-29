<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('institute_profiles', function (Blueprint $table) {
            $table->id();
            $table->json('names')->nullable();
            $table->json('contact')->nullable();
            $table->timestamps();
            $table->index('updated_at');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('institute_profiles');
    }
};
