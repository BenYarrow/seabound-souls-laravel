<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The owner account is seeded from config('admin.*') (env-driven). Keyed on
 * email via updateOrCreate so a changed ADMIN_PASSWORD rotates the password on
 * re-seed rather than creating a second user.
 */
class AdminUserSeederTest extends TestCase
{
    public function test_it_seeds_the_owner_from_config(): void
    {
        config(['admin.email' => 'owner@example.com', 'admin.password' => 'secret-one']);

        $this->seed(AdminUserSeeder::class);

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Seabound Souls', $user->name);
        $this->assertTrue(Hash::check('secret-one', $user->password));
    }

    public function test_reseeding_rotates_the_password_without_duplicating(): void
    {
        config(['admin.email' => 'owner@example.com', 'admin.password' => 'secret-one']);
        $this->seed(AdminUserSeeder::class);

        config(['admin.password' => 'secret-two']);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', 'owner@example.com')->count());
        $this->assertTrue(
            Hash::check('secret-two', User::where('email', 'owner@example.com')->first()->password)
        );
    }
}
