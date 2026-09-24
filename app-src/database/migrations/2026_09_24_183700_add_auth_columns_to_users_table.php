<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_type', 10)->default('local')->index()->after('password');
            $table->string('ad_username', 100)->nullable()->unique()->after('auth_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'ad_username']);
        });
    }
};
