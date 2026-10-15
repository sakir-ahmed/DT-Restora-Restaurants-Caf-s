<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RootAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('admin.password');

        if (! is_string($password) || $password === '') {
            $password = bin2hex(random_bytes(12));

            if ($this->command !== null) {
                $this->command->warn('ADMIN_PASSWORD is not set in .env. Generated a one-time password: '.$password);
            }

        }

        $user = User::query()->updateOrCreate(
            ['email' => config('admin.email')],
            [
                'name' => config('admin.name'),
                'username' => config('admin.username'),
                'password' => Hash::make($password),
                'is_active' => true,
                'branch_id' => null,
            ],
        );

        $user->syncRoles(['super-admin']);
    }
}
