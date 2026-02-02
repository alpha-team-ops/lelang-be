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
            $table->string('corporate_id_nip')->unique()->nullable()->after('last_login')
                ->comment('Corporate ID or NIP for portal users');
            $table->string('directorate')->nullable()->after('corporate_id_nip')
                ->comment('Directorate for portal users');
            $table->enum('user_type', ['STAFF', 'PORTAL'])->default('STAFF')->after('directorate')
                ->comment('STAFF: dashboard user, PORTAL: public bidder user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_corporate_id_nip_unique');
            $table->dropColumn(['corporate_id_nip', 'directorate', 'user_type']);
        });
    }
};
