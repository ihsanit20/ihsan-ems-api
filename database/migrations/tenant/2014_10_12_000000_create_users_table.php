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
            $table->string('email', 191)->nullable()->index();
            $table->string('password')->nullable();

            $table->enum('role', [
                'Developer',
                'Owner',
                'Admin',
                'Teacher',
                'Accountant',
                'Guardian',
                'Student'
            ])->default('Guardian');

            $table->string('photo')->nullable()->comment('User profile photo');

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['name', 'role']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('users');
    }
};
