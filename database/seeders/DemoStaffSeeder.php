<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PayBasis;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoStaffSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $branches = Branch::query()->whereIn('code', ['BR01', 'BR02', 'BR03'])->get()->keyBy('code');
        $password = Hash::make('Password123');

        $accounts = [
            ['name' => 'Omar Faruque', 'role' => 'owner', 'username' => 'owner', 'email' => 'owner@dtrestora.local', 'branch' => null],
            ['name' => 'Shahana Begum', 'role' => 'branch-manager', 'username' => 'manager', 'email' => 'manager@dtrestora.local', 'branch' => 'BR01'],
            ['name' => 'Rifat Hasan', 'role' => 'cashier', 'username' => 'cashier', 'email' => 'cashier@dtrestora.local', 'branch' => 'BR01'],
            ['name' => 'Jahangir Alam', 'role' => 'waiter', 'username' => 'waiter', 'email' => 'waiter@dtrestora.local', 'branch' => 'BR01'],
            ['name' => 'Mizanur Rahman', 'role' => 'kitchen-manager', 'username' => 'kitchen', 'email' => 'kitchen@dtrestora.local', 'branch' => 'BR01'],
            ['name' => 'Faria Akter', 'role' => 'accountant', 'username' => 'accountant', 'email' => 'accountant@dtrestora.local', 'branch' => null],
            ['name' => 'Sohel Rana', 'role' => 'rider', 'username' => 'rider', 'email' => 'rider@dtrestora.local', 'branch' => 'BR01'],
            ['name' => 'Mahmudul Hasan', 'role' => 'branch-manager', 'username' => 'manager.dhanmondi', 'email' => 'manager.dhanmondi@dtrestora.local', 'branch' => 'BR02'],
            ['name' => 'Liza Akter', 'role' => 'cashier', 'username' => 'cashier.dhanmondi', 'email' => 'cashier.dhanmondi@dtrestora.local', 'branch' => 'BR02'],
            ['name' => 'Tanvir Ahmed', 'role' => 'branch-manager', 'username' => 'manager.uttara', 'email' => 'manager.uttara@dtrestora.local', 'branch' => 'BR03'],
            ['name' => 'Priya Das', 'role' => 'cashier', 'username' => 'cashier.uttara', 'email' => 'cashier.uttara@dtrestora.local', 'branch' => 'BR03'],
            ['name' => 'Rubel Mia', 'role' => 'rider', 'username' => 'rider.rubel', 'email' => 'rider.rubel@dtrestora.local', 'branch' => 'BR02'],
            ['name' => 'Kamal Hossain', 'role' => 'rider', 'username' => 'rider.kamal', 'email' => 'rider.kamal@dtrestora.local', 'branch' => 'BR03'],
        ];

        $users = [];

        foreach ($accounts as $account) {
            $branchId = $account['branch'] !== null ? $branches->get($account['branch'])?->id : null;

            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'username' => $account['username'],
                    'password' => $password,
                    'branch_id' => $branchId,
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$account['role']]);
            $users[$account['username']] = $user;
        }

        Employee::query()
            ->whereIn('branch_id', $branches->pluck('id'))
            ->whereNull('mobile')
            ->whereDoesntHave('attendances')
            ->delete();

        $roster = [
            'BR01' => [
                ['Shahana Begum', 'শাহানা বেগম', 'Branch Manager', '8801711100101', PayBasis::Monthly, 65000, 'manager', 'EMP-G-01'],
                ['Rifat Hasan', 'রিফাত হাসান', 'Cashier', '8801711100102', PayBasis::Monthly, 28000, 'cashier', 'EMP-G-02'],
                ['Jahangir Alam', 'জাহাঙ্গীর আলম', 'Waiter', '8801711100103', PayBasis::Monthly, 18000, 'waiter', 'EMP-G-03'],
                ['Mizanur Rahman', 'মিজানুর রহমান', 'Kitchen Manager', '8801711100104', PayBasis::Monthly, 42000, 'kitchen', 'EMP-G-04'],
                ['Sohel Rana', 'সোহেল রানা', 'Rider', '8801711100105', PayBasis::Daily, 800, 'rider', 'EMP-G-05'],
                ['Karim Uddin', 'করিম উদ্দিন', 'Head Chef', '8801711100106', PayBasis::Monthly, 55000, null, 'EMP-G-06'],
                ['Farhana Akter', 'ফারহানা আক্তার', 'Sous Chef', '8801711100107', PayBasis::Monthly, 38000, null, 'EMP-G-07'],
                ['Rakib Hasan', 'রাকিব হাসান', 'Tandoor Chef', '8801711100108', PayBasis::Monthly, 32000, null, 'EMP-G-08'],
                ['Sumaiya Rahman', 'সুমাইয়া রহমান', 'Pastry Chef', '8801711100109', PayBasis::Monthly, 30000, null, 'EMP-G-09'],
                ['Mitu Akter', 'মিতু আক্তার', 'Waiter', '8801711100110', PayBasis::Daily, 650, null, 'EMP-G-10'],
                ['Shakil Ahmed', 'শাকিল আহমেদ', 'Waiter', '8801711100111', PayBasis::Daily, 650, null, 'EMP-G-11'],
                ['Tasnim Chowdhury', 'তাসনিম চৌধুরী', 'Barista', '8801711100112', PayBasis::Hourly, 90, null, 'EMP-G-12'],
                ['Nasima Begum', 'নাসিমা বেগম', 'Kitchen Helper', '8801711100113', PayBasis::Daily, 550, null, 'EMP-G-13'],
                ['Abdul Malek', 'আবদুল মালেক', 'Cleaner', '8801711100114', PayBasis::Daily, 500, null, 'EMP-G-14'],
            ],
            'BR02' => [
                ['Mahmudul Hasan', 'মাহমুদুল হাসান', 'Branch Manager', '8801711100201', PayBasis::Monthly, 48000, 'manager.dhanmondi', 'EMP-D-01'],
                ['Liza Akter', 'লিজা আক্তার', 'Cashier', '8801711100202', PayBasis::Monthly, 24000, 'cashier.dhanmondi', 'EMP-D-02'],
                ['Rubel Mia', 'রুবেল মিয়া', 'Rider', '8801711100203', PayBasis::Daily, 750, 'rider.rubel', 'EMP-D-03'],
                ['Jamal Uddin', 'জামাল উদ্দিন', 'Head Chef', '8801711100204', PayBasis::Monthly, 40000, null, 'EMP-D-04'],
                ['Nipa Akter', 'নিপা আক্তার', 'Waiter', '8801711100205', PayBasis::Daily, 600, null, 'EMP-D-05'],
                ['Polash Chandra', 'পলাশ চন্দ্র', 'Kitchen Helper', '8801711100206', PayBasis::Daily, 500, null, 'EMP-D-06'],
                ['Ruma Khatun', 'রুমা খাতুন', 'Cashier', '8801711100207', PayBasis::Hourly, 80, null, 'EMP-D-07'],
            ],
            'BR03' => [
                ['Tanvir Ahmed', 'তানভীর আহমেদ', 'Branch Manager', '8801711100301', PayBasis::Monthly, 45000, 'manager.uttara', 'EMP-U-01'],
                ['Priya Das', 'প্রিয়া দাস', 'Cashier', '8801711100302', PayBasis::Monthly, 22000, 'cashier.uttara', 'EMP-U-02'],
                ['Kamal Hossain', 'কামাল হোসেন', 'Rider', '8801711100303', PayBasis::Daily, 750, 'rider.kamal', 'EMP-U-03'],
                ['Shahidul Islam', 'শহিদুল ইসলাম', 'Head Chef', '8801711100304', PayBasis::Monthly, 38000, null, 'EMP-U-04'],
                ['Anika Sultana', 'আনিকা সুলতানা', 'Waiter', '8801711100305', PayBasis::Daily, 600, null, 'EMP-U-05'],
                ['Belal Hossain', 'বেলাল হোসেন', 'Kitchen Helper', '8801711100306', PayBasis::Hourly, 70, null, 'EMP-U-06'],
            ],
        ];

        foreach ($roster as $code => $people) {
            $branch = $branches->get($code);

            if ($branch === null) {
                continue;
            }

            foreach ($people as $person) {
                [$name, $nameBn, $designation, $mobile, $basis, $rate, $username, $deviceId] = $person;
                $pay = Employee::payAttributes([
                    'pay_basis' => $basis->value,
                    'pay_rate' => $rate,
                ]);

                Employee::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'mobile' => $mobile],
                    [
                        'user_id' => $username !== null ? $users[$username]->id : null,
                        'name' => $name,
                        'name_bn' => $nameBn,
                        'designation' => $designation,
                        'device_user_id' => $deviceId,
                        'joined_at' => now()->subMonths(random_int(4, 36))->toDateString(),
                        'is_active' => true,
                        ...$pay,
                    ],
                );
            }
        }

        $riders = [
            'rider' => ['motorcycle', 'DHA-11-4521', true],
            'rider.rubel' => ['motorcycle', 'DHA-15-8830', true],
            'rider.kamal' => ['bicycle', null, false],
        ];

        foreach ($riders as $username => [$vehicle, $plate, $onDuty]) {
            Rider::query()->updateOrCreate(
                ['user_id' => $users[$username]->id],
                ['vehicle' => $vehicle, 'plate' => $plate, 'is_on_duty' => $onDuty, 'is_active' => true],
            );
        }
    }
}
