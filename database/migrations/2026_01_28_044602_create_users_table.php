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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('email', 100)->unique();
            $table->string('password_hash', 255);
            $table->enum('role', ['ADMIN', 'MODERATOR'])->default('MODERATOR');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->string('organization_code', 50);
            $table->boolean('email_verified')->default(false);
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
            
            $table->foreign('organization_code')->references('code')->on('organizations')->onDelete('cascade');
            $table->index('email');
            $table->index('organization_code');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
