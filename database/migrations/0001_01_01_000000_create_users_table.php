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
        Schema::create('organizations', function (Blueprint $table) {
            $table->string('code', 50)->primary();
            $table->string('name', 100);
            $table->string('email', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('logo', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->string('currency', 3)->default('IDR');
            $table->string('language', 2)->default('id');
            $table->boolean('email_notifications')->default(true);
            $table->boolean('auction_notifications')->default(true);
            $table->boolean('bid_notifications')->default(true);
            $table->boolean('two_factor_auth')->default(false);
            $table->boolean('maintenance_mode')->default(false);
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
            $table->index('status');
            $table->index('name');
            $table->index('email');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('email', 100)->unique();
            $table->string('password_hash', 255);
            $table->enum('role', ['ADMIN', 'MODERATOR', 'MEMBER'])->default('MEMBER');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->string('organization_code', 50)->nullable();
            $table->boolean('email_verified')->default(false);
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
            
            $table->foreign('organization_code')->references('code')->on('organizations')->onDelete('cascade');
            $table->index('email');
            $table->index('organization_code');
            $table->index('status');
        });

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token_hash', 255)->unique();
            $table->timestamp('expires_at');
            $table->boolean('revoked')->default(false);
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->index('expires_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token_hash', 255)->unique();
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });

        Schema::create('auth_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('email', 100);
            $table->string('action', 50);
            $table->string('status', 20);
            $table->string('ip_address', 50)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('user_id');
            $table->index('email');
            $table->index('created_at');
        });

        Schema::create('org_settings_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('organization_code', 50);
            $table->uuid('changed_by');
            $table->string('field_name', 100)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            
            $table->foreign('organization_code')->references('code')->on('organizations')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('organization_code');
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_settings_history');
        Schema::dropIfExists('auth_logs');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('refresh_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizations');
    }
};
