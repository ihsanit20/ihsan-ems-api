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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();

            // Basic meta
            $table->string('name');
            $table->string('domain')->unique();   // e.g. demo.local.test

            // Tenant DB connection info
            $table->string('db_name')->unique();  // e.g. ems_demo
            $table->string('db_host')->nullable();
            $table->unsignedInteger('db_port')->nullable();
            $table->string('db_username')->nullable();
            $table->text('db_password')->nullable(); // encrypted/hashed at app layer

            // Status
            $table->boolean('is_active')->default(true);

            // Initial branding (store S3 keys or relative paths, not full URLs)
            // Example payload:
            // {
            //   "logo_key": "tenants/1/branding/logo.svg",
            //   "favicon_16_key": "tenants/1/branding/favicon-16.png",
            //   "favicon_32_key": "tenants/1/branding/favicon-32.png"
            // }
            $table->json('branding')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
