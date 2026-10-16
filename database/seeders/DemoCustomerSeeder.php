<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AggregatorChannel;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryZone;
use App\Models\LoyaltySetting;
use Illuminate\Database\Seeder;

class DemoCustomerSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        LoyaltySetting::query()->updateOrCreate([], [
            'earn_rate' => 1,
            'point_value' => '1.00',
            'expiry_days' => 365,
            'is_enabled' => true,
        ]);

        foreach ([
            ['foodpanda', 'Foodpanda', '25.00'],
            ['pathao_food', 'Pathao Food', '20.00'],
            ['foodi', 'Foodi', '18.00'],
        ] as [$key, $name, $commission]) {
            AggregatorChannel::query()->updateOrCreate(
                ['key' => $key],
                ['name' => $name, 'commission_percent' => $commission, 'is_active' => true],
            );
        }

        $gulshan = Branch::query()->where('code', 'BR01')->first();

        foreach ([
            ['WELCOME10', 'percent', '10.00', '300.00', null, null, ['website'], 500, 1],
            ['GULSHAN50', 'fixed', '50.00', '400.00', $gulshan?->id, null, null, null, 2],
            ['LUNCH15', 'percent', '15.00', '250.00', null, null, ['pos', 'website'], null, 1],
            ['BIRTHDAY100', 'fixed', '100.00', '500.00', null, null, null, null, 1],
            ['OLD20', 'percent', '20.00', '200.00', null, now()->subMonth(), ['website'], 100, 1],
        ] as [$code, $type, $value, $min, $branchId, $ends, $channels, $limit, $perCustomer]) {
            Coupon::query()->updateOrCreate(
                ['code' => $code],
                [
                    'type' => $type,
                    'value' => $value,
                    'min_order' => $min,
                    'starts_at' => now()->subMonths(2),
                    'ends_at' => $ends,
                    'usage_limit_total' => $limit,
                    'usage_limit_per_customer' => $perCustomer,
                    'channels' => $channels,
                    'branch_id' => $branchId,
                    'is_active' => $code !== 'OLD20',
                ],
            );
        }

        $zones = DeliveryZone::query()->where('is_active', true)->get();
        $names = $this->names();

        foreach ($names as $index => [$name, $area]) {
            $mobile = '8801713'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT);
            $zone = $zones->firstWhere('name', $area) ?? ($zones->isNotEmpty() ? $zones->random() : null);

            $customer = Customer::query()->updateOrCreate(
                ['mobile' => $mobile],
                [
                    'name' => $name,
                    'email' => $index < 24 ? strtolower(str_replace(' ', '.', $name)).'@example.com' : null,
                    'locale' => $index % 3 === 0 ? 'bn' : 'en',
                    'is_active' => true,
                    'mobile_verified_at' => now()->subDays(20 + $index),
                    'birthday' => $index % 7 === 0 ? now()->subYears(24 + ($index % 15))->month(now()->month)->day(min(28, now()->day))->toDateString() : null,
                ],
            );

            $customer->forceFill([
                'created_at' => now()->subDays(40 + ($index * 3)),
                'updated_at' => now()->subDays($index % 12),
            ])->save();

            if ($zone !== null) {
                CustomerAddress::query()->updateOrCreate(
                    ['customer_id' => $customer->id, 'label' => 'Home'],
                    [
                        'delivery_zone_id' => $zone->id,
                        'address' => 'House '.($index + 4).', '.$area.', Dhaka',
                        'landmark' => $index % 2 === 0 ? 'Near the main road' : 'Opposite the mosque',
                    ],
                );
            }
        }
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function names(): array
    {
        return [
            ['Nusrat Jahan', 'Gulshan'],
            ['Tahmid Rahman', 'Banani'],
            ['Sadia Islam', 'Dhanmondi'],
            ['Arif Chowdhury', 'Mohammadpur'],
            ['Mehzabin Haque', 'Uttara Sector 4-6'],
            ['Fahim Ahmed', 'Gulshan'],
            ['Lamisa Khan', 'Baridhara'],
            ['Sabbir Hossain', 'Lalmatia'],
            ['Nowrin Sultana', 'Niketan'],
            ['Imtiaz Karim', 'Kalabagan'],
            ['Raisa Ahmed', 'Uttara Sector 7-12'],
            ['Tonmoy Das', 'Abdullahpur'],
            ['Farhana Yasmin', 'Gulshan'],
            ['Adnan Kabir', 'Banani'],
            ['Maliha Noor', 'Dhanmondi'],
            ['Shafiqul Islam', 'Mohammadpur'],
            ['Anika Rahman', 'Airport'],
            ['Jubayer Alam', 'Gulshan'],
            ['Puja Sen', 'Dhanmondi'],
            ['Hasib Mahmud', 'Banani'],
            ['Orpa Chowdhury', 'Uttara Sector 4-6'],
            ['Nabil Khan', 'Lalmatia'],
            ['Samia Akter', 'Baridhara'],
            ['Rashedul Hasan', 'Kalabagan'],
            ['Mim Rahman', 'Niketan'],
            ['Tawsif Ahmed', 'Mohammadpur'],
            ['Ishrat Jahan', 'Gulshan'],
            ['Parvez Alam', 'Dhanmondi'],
            ['Shaila Nasrin', 'Uttara Sector 7-12'],
            ['Omar Siddique', 'Banani'],
            ['Nafisa Haque', 'Airport'],
            ['Zahid Hassan', 'Abdullahpur'],
            ['Rumana Parvin', 'Lalmatia'],
            ['Asif Iqbal', 'Gulshan'],
            ['Tanha Islam', 'Dhanmondi'],
            ['Mahin Rahman', 'Banani'],
            ['Sanjida Akter', 'Mohammadpur'],
            ['Fardin Ahmed', 'Kalabagan'],
            ['Lubaba Noor', 'Niketan'],
            ['Shovon Roy', 'Uttara Sector 4-6'],
            ['Nadia Karim', 'Baridhara'],
            ['Rakibul Islam', 'Dhanmondi'],
            ['Eva Sultana', 'Gulshan'],
            ['Mamun Rashid', 'Airport'],
            ['Chaiti Das', 'Uttara Sector 7-12'],
            ['Samin Yeasar', 'Banani'],
            ['Ayesha Siddika', 'Lalmatia'],
            ['Hridoy Ahmed', 'Mohammadpur'],
            ['Maisha Rahman', 'Gulshan'],
            ['Sazzad Hossain', 'Dhanmondi'],
            ['Tumpa Akter', 'Kalabagan'],
            ['Naimur Rahman', 'Abdullahpur'],
            ['Fariha Islam', 'Banani'],
            ['Shahriar Kabir', 'Niketan'],
            ['Mousumi Begum', 'Uttara Sector 4-6'],
            ['Alvi Hasan', 'Baridhara'],
            ['Sadman Sakib', 'Dhanmondi'],
            ['Jannatul Ferdous', 'Gulshan'],
            ['Prova Chowdhury', 'Mohammadpur'],
            ['Irfan Ahmed', 'Lalmatia'],
            ['Sharna Islam', 'Airport'],
            ['Bappi Rahman', 'Uttara Sector 7-12'],
            ['Noshin Tarannum', 'Gulshan'],
            ['Rafiul Islam', 'Banani'],
            ['Moumita Sen', 'Dhanmondi'],
            ['Habib Khan', 'Kalabagan'],
            ['Ananya Das', 'Niketan'],
            ['Shorif Ahmed', 'Abdullahpur'],
            ['Meherun Nesa', 'Baridhara'],
            ['Tarek Rahman', 'Mohammadpur'],
            ['Saba Noor', 'Uttara Sector 4-6'],
            ['Kawsar Ahmed', 'Gulshan'],
            ['Rifah Islam', 'Dhanmondi'],
            ['Pial Hasan', 'Banani'],
            ['Sumaiya Haque', 'Lalmatia'],
            ['Nazmul Karim', 'Airport'],
        ];
    }
}
