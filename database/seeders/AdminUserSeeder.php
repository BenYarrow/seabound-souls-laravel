<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds (or updates) the single owner account from config('admin.*'), which is
 * env-driven. Idempotent via updateOrCreate keyed on email: re-running after an
 * ADMIN_PASSWORD change rotates the password rather than creating a duplicate.
 * Run standalone on deploy: `php artisan db:seed --class=AdminUserSeeder`.
 */
class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::updateOrCreate(
            ['email' => config('admin.email')],
            [
                'name' => 'Seabound Sessions',
                'password' => Hash::make(config('admin.password')),
                'role' => User::ROLE_OWNER,
            ],
        );
    }
}
