<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id(); // এক টেন্যান্টে সাধারণত ১টাই রো রাখব (কন্ট্রোলার সেটা ম্যানেজ করবে)
            $table->string('name');                 // Display name (branding override)
            $table->string('short_name')->nullable();

            // JSON ব্লকগুলো নমনীয় রাখছি—ভবিষ্যতে ফিল্ড যোগ করলেও মাইগ্রেশন কম লাগবে
            $table->json('branding')->nullable();   // {logoUrl, faviconUrl, primaryColor, secondaryColor}
            $table->json('locale')->nullable();     // {default, supported[], numberSystem, calendarMode, timezone, dateFormat, timeFormat}
            $table->json('currency')->nullable();   // {code, symbol, position}
            $table->json('features')->nullable();   // {admission, attendance, fees, exam, ...}
            $table->json('policy')->nullable();     // {maxUploadMB, ...}

            $table->boolean('maintenance')->default(false); // UI maintenance মোড

            $table->timestamps();

            // চাইলে unique একটাই রো enforce করতে পারেন (DB-level workaround)
            // $table->unique('id'); // (id primary-ই unique)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
