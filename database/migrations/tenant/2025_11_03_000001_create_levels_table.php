<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // e.g., Primary, Secondary, Hifz
            $table->string('code', 32)->nullable();         // e.g., PRI, SEC, HZ
            $table->unsignedSmallInteger('sort_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name'); // tenant-wise unique
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
