<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'name' => 'DT Restora Gulshan',
                'name_bn' => 'ডিটি রেস্টোরা গুলশান',
                'code' => 'BR01',
                'phone' => '01711000001',
                'address' => 'House 12, Road 55, Gulshan 2',
                'city' => 'Dhaka',
                'latitude' => '23.7925000',
                'longitude' => '90.4078000',
            ],
            [
                'name' => 'DT Restora Dhanmondi',
                'name_bn' => 'ডিটি রেস্টোরা ধানমন্ডি',
                'code' => 'BR02',
                'phone' => '01711000002',
                'address' => 'Road 27, Dhanmondi',
                'city' => 'Dhaka',
                'latitude' => '23.7461000',
                'longitude' => '90.3760000',
            ],
        ];

        foreach ($branches as $branch) {
            $model = Branch::query()->updateOrCreate(['code' => $branch['code']], $branch);
            $model->update(['online_ordering_enabled' => true]);
        }

        $gulshan = Branch::query()->where('code', 'BR01')->firstOrFail();

        foreach ([
            ['name' => 'Gulshan - Banani', 'fee' => '60.00', 'min_order' => '200.00', 'eta' => 30],
            ['name' => 'Dhanmondi - Mohammadpur', 'fee' => '50.00', 'min_order' => '150.00', 'eta' => 35],
            ['name' => 'Uttara', 'fee' => '80.00', 'min_order' => '300.00', 'eta' => 45],
        ] as $zone) {
            DeliveryZone::query()->updateOrCreate(
                ['branch_id' => $gulshan->id, 'name' => $zone['name']],
                ['fee' => $zone['fee'], 'min_order' => $zone['min_order'], 'eta_minutes' => $zone['eta'], 'is_active' => true],
            );
        }
    }
}
