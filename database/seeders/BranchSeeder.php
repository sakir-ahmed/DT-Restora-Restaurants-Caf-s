<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $hours = [
            ['days' => ['sat', 'sun', 'mon', 'tue', 'wed', 'thu'], 'opens' => '11:00', 'closes' => '23:30'],
            ['days' => ['fri'], 'opens' => '15:00', 'closes' => '23:30'],
        ];

        $branches = [
            [
                'name' => 'DT Restora Gulshan',
                'name_bn' => 'ডিটি রেস্টোরা গুলশান',
                'code' => 'BR01',
                'phone' => '01711000001',
                'address' => 'House 12, Road 55, Gulshan 2, Dhaka 1212',
                'city' => 'Dhaka',
                'latitude' => '23.7925000',
                'longitude' => '90.4078000',
                'vat_percent' => '15.00',
                'service_charge_percent' => '5.00',
                'receipt_footer' => 'Thank you for dining with DT Restora. Follow @dtrestora',
                'zones' => [
                    ['name' => 'Gulshan', 'fee' => '50.00', 'min_order' => '250.00', 'eta' => 25],
                    ['name' => 'Banani', 'fee' => '60.00', 'min_order' => '250.00', 'eta' => 30],
                    ['name' => 'Baridhara', 'fee' => '70.00', 'min_order' => '300.00', 'eta' => 35],
                    ['name' => 'Niketan', 'fee' => '60.00', 'min_order' => '250.00', 'eta' => 30],
                ],
            ],
            [
                'name' => 'DT Restora Dhanmondi',
                'name_bn' => 'ডিটি রেস্টোরা ধানমন্ডি',
                'code' => 'BR02',
                'phone' => '01711000002',
                'address' => 'House 43, Road 27, Dhanmondi, Dhaka 1209',
                'city' => 'Dhaka',
                'latitude' => '23.7461000',
                'longitude' => '90.3760000',
                'vat_percent' => '15.00',
                'service_charge_percent' => '0.00',
                'receipt_footer' => 'Thank you. Call 01711000002 to reorder.',
                'zones' => [
                    ['name' => 'Dhanmondi', 'fee' => '40.00', 'min_order' => '200.00', 'eta' => 25],
                    ['name' => 'Mohammadpur', 'fee' => '50.00', 'min_order' => '200.00', 'eta' => 30],
                    ['name' => 'Lalmatia', 'fee' => '50.00', 'min_order' => '200.00', 'eta' => 30],
                    ['name' => 'Kalabagan', 'fee' => '45.00', 'min_order' => '200.00', 'eta' => 25],
                ],
            ],
            [
                'name' => 'DT Restora Uttara',
                'name_bn' => 'ডিটি রেস্টোরা উত্তরা',
                'code' => 'BR03',
                'phone' => '01711000003',
                'address' => 'House 18, Road 7, Sector 4, Uttara, Dhaka 1230',
                'city' => 'Dhaka',
                'latitude' => '23.8682000',
                'longitude' => '90.4008000',
                'vat_percent' => '15.00',
                'service_charge_percent' => '0.00',
                'receipt_footer' => 'Thank you for visiting DT Restora Uttara.',
                'zones' => [
                    ['name' => 'Uttara Sector 4-6', 'fee' => '40.00', 'min_order' => '200.00', 'eta' => 25],
                    ['name' => 'Uttara Sector 7-12', 'fee' => '60.00', 'min_order' => '250.00', 'eta' => 35],
                    ['name' => 'Abdullahpur', 'fee' => '70.00', 'min_order' => '300.00', 'eta' => 40],
                    ['name' => 'Airport', 'fee' => '80.00', 'min_order' => '350.00', 'eta' => 40],
                ],
            ],
        ];

        foreach ($branches as $branch) {
            $zones = $branch['zones'];
            unset($branch['zones']);

            $model = Branch::query()->updateOrCreate(['code' => $branch['code']], [
                ...$branch,
                'online_ordering_enabled' => true,
                'opening_hours' => $hours,
                'is_active' => true,
            ]);

            foreach ($zones as $zone) {
                DeliveryZone::query()->updateOrCreate(
                    ['branch_id' => $model->id, 'name' => $zone['name']],
                    [
                        'fee' => $zone['fee'],
                        'min_order' => $zone['min_order'],
                        'eta_minutes' => $zone['eta'],
                        'is_active' => true,
                    ],
                );
            }

            DeliveryZone::query()
                ->where('branch_id', $model->id)
                ->whereNotIn('name', array_column($zones, 'name'))
                ->delete();

            foreach (['T01' => 'Counter 1', 'T02' => 'Counter 2', 'WEB' => 'Website'] as $code => $name) {
                DB::table('terminals')->updateOrInsert(
                    ['branch_id' => $model->id, 'code' => $code],
                    ['name' => $name, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }
}
