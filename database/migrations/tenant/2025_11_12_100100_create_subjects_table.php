<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('grade_id')
                ->constrained('grades')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name');
            $table->string('code');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['grade_id', 'code']);
            $table->index('grade_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
