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
        Schema::create('role_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('role_id')->nullable();
            $table->uuid('staff_id')->nullable();
            $table->string('action');
            $table->json('changes')->nullable();
            $table->uuid('performed_by')->nullable();
            $table->timestamp('performed_at')->useCurrent();

            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('cascade');

            $table->foreign('staff_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('performed_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('performed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_audit_logs');
    }
};
