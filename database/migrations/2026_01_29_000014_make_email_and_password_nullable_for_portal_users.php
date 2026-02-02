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
        Schema::table('users', function (Blueprint $table) {
            // Make email and password_hash nullable for portal users
            $table->string('email', 100)->nullable()->change();
            $table->string('password_hash', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Note: This down migration might fail if there are NULL values
            // Better to handle manually in production
            $table->string('email', 100)->nullable(false)->change();
            $table->string('password_hash', 255)->nullable(false)->change();
        });
    }
};
