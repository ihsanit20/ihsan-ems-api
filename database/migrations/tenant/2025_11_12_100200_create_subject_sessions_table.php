<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subject_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('session_id')
                ->constrained('academic_sessions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete(); // সেশন ডিলিট হলে লিংকড রো যাবে

            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnUpdate()
                ->restrictOnDelete(); // সাবজেক্ট ডিলিট ব্লক (থাকলে)

            $table->smallInteger('order_index')->default(0);
            $table->string('book_name')->nullable();

            $table->timestamps();

            // একই সেশনে একই সাবজেক্ট একবারই
            $table->unique(['session_id', 'subject_id']);

            $table->index(['session_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_sessions');
    }
};