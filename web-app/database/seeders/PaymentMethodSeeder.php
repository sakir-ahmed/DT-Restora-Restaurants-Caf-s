<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['code' => 'cash', 'name' => 'Cash', 'name_bn' => 'নগদ', 'type' => 'cash', 'is_system' => true],
            ['code' => 'bkash', 'name' => 'bKash', 'name_bn' => 'বিকাশ', 'type' => 'wallet', 'is_system' => true],
            ['code' => 'nagad', 'name' => 'Nagad', 'name_bn' => 'নগদ (মোবাইল)', 'type' => 'wallet', 'is_system' => true],
            ['code' => 'rocket', 'name' => 'Rocket', 'name_bn' => 'রকেট', 'type' => 'wallet'],
            ['code' => 'upay', 'name' => 'Upay', 'name_bn' => 'উপায়', 'type' => 'wallet'],
            ['code' => 'card', 'name' => 'Card / SSLCommerz', 'name_bn' => 'কার্ড / এসএসএলকমার্জ', 'type' => 'card', 'is_system' => true],
            ['code' => 'ebl_pos', 'name' => 'EBL POS', 'name_bn' => 'ইবিএল পিওএস', 'type' => 'card'],
            ['code' => 'city_pos', 'name' => 'City POS', 'name_bn' => 'সিটি পিওএস', 'type' => 'card'],
            ['code' => 'mtb_pos', 'name' => 'MTB POS', 'name_bn' => 'এমটিবি পিওএস', 'type' => 'card'],
            ['code' => 'dbbl_pos', 'name' => 'DBBL POS', 'name_bn' => 'ডিবিবিএল পিওএস', 'type' => 'card'],
        ];

        foreach ($methods as $position => $method) {
            PaymentMethod::query()->updateOrCreate(
                ['code' => $method['code']],
                $method + ['is_active' => true, 'sort_order' => $position + 1],
            );
        }
    }
}
