<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create or get test organization
        $org = Organization::firstOrCreate(
            ['code' => 'ORG-DERALY-001'],
            [
                'name' => 'Deraly Development',
                'description' => 'Development organization for testing',
                'status' => 'ACTIVE',
                'portal_invitation_code' => $this->generatePortalInvitationCode(),
                'portal_invitation_active' => true,
            ]
        );

        // Create or update test admin user
        User::updateOrCreate(
            ['email' => 'alpha.dev@deraly.id'],
            [
                'id' => User::where('email', 'alpha.dev@deraly.id')->first()?->id ?? (string) Str::uuid(),
                'name' => 'Alpha Dev',
                'password_hash' => Hash::make('Real1Novation!'),
                'role' => 'ADMIN',
                'status' => 'ACTIVE',
                'organization_code' => $org->code,
                'email_verified' => true,
            ]
        );

        // Create or update test moderator user
        User::updateOrCreate(
            ['email' => 'moderator@deraly.id'],
            [
                'id' => User::where('email', 'moderator@deraly.id')->first()?->id ?? (string) Str::uuid(),
                'name' => 'Test Moderator',
                'password_hash' => Hash::make('SecurePassword123!'),
                'role' => 'MODERATOR',
                'status' => 'ACTIVE',
                'organization_code' => $org->code,
                'email_verified' => true,
            ]
        );

        // Call role and permission seeder
        $this->call(RolePermissionSeeder::class);

        // Call auction seeder
        $this->call(AuctionSeeder::class);

        // Call bid seeder
        $this->call(BidSeeder::class);
    }

    /**
     * Generate unique portal invitation code
     * 
     * @return string
     */
    private function generatePortalInvitationCode(): string
    {
        do {
            $code = 'PORTAL-' . strtoupper(Str::random(8));
        } while (Organization::where('portal_invitation_code', $code)->exists());

        return $code;
    }
}
