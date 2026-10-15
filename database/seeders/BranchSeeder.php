<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
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
            Branch::query()->updateOrCreate(['code' => $branch['code']], $branch);
        }
    }
}
