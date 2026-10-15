<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoStaffSeeder extends Seeder
{
    public function run(): void
    {
        if (config('app.env') === 'testing') {
            return;
        }

        $branch = Branch::query()->where('code', 'BR01')->first();

        $staff = [
            ['name' => 'Omar Faruque', 'role' => 'owner', 'username' => 'owner', 'email' => 'owner@dtrestora.local', 'branch_id' => null],
            ['name' => 'Shahana Begum', 'role' => 'branch-manager', 'username' => 'manager', 'email' => 'manager@dtrestora.local', 'branch_id' => $branch?->id],
            ['name' => 'Rifat Hasan', 'role' => 'cashier', 'username' => 'cashier', 'email' => 'cashier@dtrestora.local', 'branch_id' => $branch?->id],
            ['name' => 'Jahangir Alam', 'role' => 'waiter', 'username' => 'waiter', 'email' => 'waiter@dtrestora.local', 'branch_id' => $branch?->id],
            ['name' => 'Mizanur Rahman', 'role' => 'kitchen-manager', 'username' => 'kitchen', 'email' => 'kitchen@dtrestora.local', 'branch_id' => $branch?->id],
            ['name' => 'Faria Akter', 'role' => 'accountant', 'username' => 'accountant', 'email' => 'accountant@dtrestora.local', 'branch_id' => null],
            ['name' => 'Sohel Rana', 'role' => 'rider', 'username' => 'rider', 'email' => 'rider@dtrestora.local', 'branch_id' => $branch?->id],
        ];

        foreach ($staff as $member) {
            $user = User::query()->updateOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'username' => $member['username'],
                    'password' => Hash::make('Password123'),
                    'branch_id' => $member['branch_id'],
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$member['role']]);
        }
    }
}
