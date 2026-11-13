<?php

declare(strict_types=1);

namespace Database\Seeders\Production\HillsTable;

/**
 * The Hills Table menu book. Prices are in taka. `serves` is the number of
 * people a plate feeds: 1 means one person, 2 means one to two persons.
 */
final class Catalog
{
    /**
     * @return list<array{name: string, name_bn: string, items: list<array{number: int, name: string, name_bn: string, price: int, serves: int}>}>
     */
    public static function categories(): array
    {
        return [
            [
                'name' => 'Chicken and Duck',
                'name_bn' => 'মুরগি ও হাঁস',
                'items' => [
                    self::item(1, 'Pahari Deshi Murgi', 'পাহাড়ি দেশি মুরগি', 350, 2),
                    self::item(2, 'Kolapatar Murgi', 'কলাপাতার মুরগি', 250, 2),
                    self::item(16, 'Pahari Has Bhuna', 'পাহাড়ি হাঁস ভুনা', 350, 1),
                    self::item(21, 'Chicken Mungdi', 'চিকেন মুন্ডি', 150, 1),
                ],
            ],
            [
                'name' => 'Fish and Seafood',
                'name_bn' => 'মাছ ও সি-ফুড',
                'items' => [
                    self::item(12, 'Hill Spice Prawns', 'হিল স্পাইস চিংড়ি', 300, 1),
                    self::item(13, 'Bhapa Elish', 'ভাপা ইলিশ', 350, 1),
                    self::item(14, 'Pahari Fish Laksu', 'পাহাড়ি মাছের লাকসু', 220, 2),
                    self::item(17, 'Hills Table Seafood', 'হিলস টেবিল সি-ফুড', 350, 1),
                    self::item(18, 'Hill Style Crab Curry', 'পাহাড়ি স্টাইলের কাঁকড়া কারি', 350, 1),
                    self::item(19, 'Loitta Fry', 'লইট্টা ফ্রাই', 200, 2),
                ],
            ],
            [
                'name' => 'Rice and Pitha',
                'name_bn' => 'ভাত ও পিঠা',
                'items' => [
                    self::item(3, 'Sticky Rice Ball', 'বিন্নি চালের বল (স্টিকি রাইস বল)', 100, 1),
                    self::item(5, 'Bini Chaler Pitha', 'বিন্নি চালের পিঠা', 80, 1),
                    self::item(7, 'Coconut Rice', 'কোকোনাট রাইস', 130, 1),
                ],
            ],
            [
                'name' => 'Vegetables, Daal and Sides',
                'name_bn' => 'সবজি, ডাল ও সাইড',
                'items' => [
                    self::item(4, 'Begun Gabi', 'বেগুন গাবি', 120, 2),
                    self::item(6, 'Hill Style Daal', 'পাহাড়ি স্টাইলে ডাল', 120, 2),
                    self::item(8, 'Cha Pata Salad', 'চা পাতার সালাদ', 160, 1),
                    self::item(9, 'Hill Style Mocha (Vaji)', 'পাহাড়ি স্টাইলে মোচা (ভাজি)', 120, 1),
                    self::item(10, 'Borboti Laksu', 'বরবটি লাকসু', 120, 2),
                    self::item(11, 'Mushroom Vaji', 'মাশরুম ভাজি', 160, 1),
                    self::item(15, 'Dim Laksu', 'ডিম লাকসু', 140, 2),
                ],
            ],
            [
                'name' => 'Hill Specials',
                'name_bn' => 'পাহাড়ি বিশেষ',
                'items' => [
                    self::item(20, 'Mungdi', 'মুন্ডি', 100, 1),
                ],
            ],
        ];
    }

    /**
     * @return array{number: int, name: string, name_bn: string, price: int, serves: int}
     */
    private static function item(int $number, string $name, string $nameBn, int $price, int $serves): array
    {
        return ['number' => $number, 'name' => $name, 'name_bn' => $nameBn, 'price' => $price, 'serves' => $serves];
    }
}
