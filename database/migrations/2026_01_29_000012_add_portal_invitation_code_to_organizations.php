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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('portal_invitation_code')->unique()->nullable()->after('description')
                ->comment('Reusable invitation code for portal users (auto-generated, can be regenerated)');
            $table->boolean('portal_invitation_active')->default(true)->after('portal_invitation_code')
                ->comment('Whether the invitation code is active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['portal_invitation_code', 'portal_invitation_active']);
        });
    }
};
