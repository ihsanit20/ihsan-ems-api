<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/xxxx_xx_xx_add_db_credentials_to_tenants.php
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->string('db_host')->nullable()->after('db_name');
            $t->unsignedInteger('db_port')->nullable()->after('db_host');
            $t->string('db_username')->nullable()->after('db_port');
            $t->text('db_password')->nullable()->after('db_username'); // encrypted
        });
    }
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->dropColumn(['db_host', 'db_port', 'db_username', 'db_password']);
        });
    }
};
