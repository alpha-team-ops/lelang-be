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
        // Create test organization
        $org = Organization::create([
            'code' => 'ORG-DERALY-001',
            'name' => 'Deraly Development',
            'description' => 'Development organization for testing',
            'status' => 'ACTIVE',
        ]);

        // Create test admin user
        User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Alpha Dev',
            'email' => 'alpha.dev@deraly.id',
            'password_hash' => Hash::make('SecurePassword123!'),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'organization_code' => $org->code,
            'email_verified' => true,
        ]);

        // Create test moderator user
        User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Moderator',
            'email' => 'moderator@deraly.id',
            'password_hash' => Hash::make('SecurePassword123!'),
            'role' => 'MODERATOR',
            'status' => 'ACTIVE',
            'organization_code' => $org->code,
            'email_verified' => true,
        ]);
    }
}
