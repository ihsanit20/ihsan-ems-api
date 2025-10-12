<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('phone', 32)->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            // চাইলে এখানে index/constraints আরেকটু কাস্টমাইজ করতে পারেন
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('users');
    }
};
