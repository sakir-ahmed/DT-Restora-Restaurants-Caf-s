<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * Arman's Kitchen menu as printed on the in-house menu card, the Qasr Al
 * Mandi pre-order card and the promotion posters. Prices are in taka.
 * `photo` is the file name (without extension) under
 * database/seeders/assets/armans-kitchen/menu, or null when there is no photo yet.
 */
final class ArmansKitchenCatalog
{
    /**
     * @return array<int, array{name: string, name_bn: string, seed_once?: bool, items: array<int, array<string, mixed>>}>
     */
    public static function categories(): array
    {
        return [
            [
                'name' => 'Snacks & Fried Items',
                'name_bn' => 'স্ন্যাকস ও ফ্রাইড আইটেম',
                'items' => [
                    self::item(1, 'French Fry', 'ফ্রেঞ্চ ফ্রাই', 100, null, true),
                    self::item(2, 'Chicken Fry', 'চিকেন ফ্রাই', 120),
                    self::item(3, 'Chicken Meatbox', 'চিকেন মিটবক্স', 200, 'chicken-meatbox'),
                    self::item(4, 'Crispy Chicken Wings', 'ক্রিস্পি চিকেন উইংস', 260, 'crispy-chicken-wings'),
                    self::item(5, 'BBQ Chicken Wings (6pcs)', 'বিবিকিউ চিকেন উইংস (৬ পিস)', 280, 'bbq-chicken-wings'),
                    self::item(6, 'Cashewnut Salad', 'কাজুবাদাম সালাদ', 280, null, true),
                    self::item(7, 'Chicken Strip with Rice', 'চিকেন স্ট্রিপ উইথ রাইস', 300),
                    self::item(8, 'Chicken Strip with Fries', 'চিকেন স্ট্রিপ উইথ ফ্রাইস', 300),
                    self::item(9, 'Fish Fry Thai (±500gm)', 'ফিশ ফ্রাই থাই (±৫০০ গ্রাম)', 300),
                ],
            ],
            [
                'name' => 'Rice & Noodles',
                'name_bn' => 'ভাত ও নুডলস',
                'items' => [
                    self::item(10, 'Fried Rice with Chicken Fry', 'ফ্রাইড রাইস উইথ চিকেন ফ্রাই', 180),
                    self::item(11, 'Fried Rice with Chili Chicken', 'ফ্রাইড রাইস উইথ চিলি চিকেন', 200, 'fried-rice-with-chili-chicken'),
                    self::item(12, 'Fried Rice with BBQ Wings (2pcs)', 'ফ্রাইড রাইস উইথ বিবিকিউ উইংস (২ পিস)', 220),
                    self::item(13, 'Fried Rice with BBQ Chicken', 'ফ্রাইড রাইস উইথ বিবিকিউ চিকেন', 220),
                    self::item(14, 'Chicken Chowmein', 'চিকেন চাউমিন', 230, 'chicken-chowmein'),
                ],
            ],
            [
                'name' => 'Sandwich & Wraps',
                'name_bn' => 'স্যান্ডউইচ ও র‍্যাপ',
                'items' => [
                    self::item(15, 'Chicken Sub Sandwich', 'চিকেন সাব স্যান্ডউইচ', 150),
                    self::item(16, 'Chicken Sharma', 'চিকেন শাওয়ার্মা', 150, 'chicken-sharma'),
                ],
            ],
            [
                'name' => 'Street Food',
                'name_bn' => 'স্ট্রিট ফুড',
                'items' => [
                    self::item(17, 'Regular Chotpoti', 'রেগুলার চটপটি', 70, 'regular-chotpoti', true),
                    self::item(18, 'Shahi Chotpoti (AK Special)', 'শাহী চটপটি (এ কে স্পেশাল)', 100, 'shahi-chotpoti', true),
                    self::item(19, 'Regular Fuchka', 'রেগুলার ফুচকা', 80, null, true),
                    self::item(20, 'Churmur (AK Special)', 'চুরমুর (এ কে স্পেশাল)', 100, null, true),
                    self::item(21, 'Doi Fuchka (AK Special)', 'দই ফুচকা (এ কে স্পেশাল)', 140, 'doi-fuchka', true),
                ],
            ],
            [
                'name' => 'Chicken Burger',
                'name_bn' => 'চিকেন বার্গার',
                'items' => [
                    self::item(22, 'Student Burger', 'স্টুডেন্ট বার্গার', 70, 'student-burger'),
                    self::item(23, 'Regular Burger', 'রেগুলার বার্গার', 120, 'regular-burger'),
                    self::item(24, 'Naga Burger', 'নাগা বার্গার', 130),
                    self::item(25, 'Cheesy Burger', 'চিজি বার্গার', 170),
                    self::item(26, 'Jumbo Burger', 'জাম্বো বার্গার', 250, 'jumbo-burger'),
                    self::item(27, 'Cheesy Jumbo Burger', 'চিজি জাম্বো বার্গার', 300, 'cheesy-jumbo-burger'),
                ],
            ],
            [
                'name' => 'Drinks & Others',
                'name_bn' => 'ড্রিংকস ও অন্যান্য',
                'items' => [
                    self::item(28, 'Rong Cha', 'রং চা', 10, null, true),
                    self::item(29, 'Masala Rong Cha', 'মসলা রং চা', 20, null, true),
                    self::item(30, 'Dudh Cha', 'দুধ চা', 30, null, true),
                    self::item(31, 'Pora Ruti', 'পোড়া রুটি', 30, null, true),
                    self::item(32, 'Regular Coffee', 'রেগুলার কফি', 40, null, true),
                    self::item(33, 'Horlicks Dudh Cha', 'হরলিকস দুধ চা', 50, null, true),
                    self::item(34, 'Lassi', 'লাচ্ছি', 100, null, true),
                    self::item(35, 'Cold Coffee', 'কোল্ড কফি', 100, null, true),
                    self::item(36, 'Chocolate Milk Shake', 'চকলেট মিল্ক শেক', 100, null, true),
                    self::item(37, 'Green Lemonade', 'গ্রিন লেমনেড', 100, 'green-lemonade', true),
                ],
            ],
            [
                'name' => 'Qasr Al Mandi (Pre-order)',
                'name_bn' => 'কাসর আল মান্দি (প্রি-অর্ডার)',
                'items' => [
                    self::mandi(38, 'Kabsa', 'কাবসা', 'Chicken + Rice + Batata Maklia + Salad + Dakus Sauce + Thum Sauce', 900, 1700, 'kabsa'),
                    self::mandi(39, 'Dajaj Mandi', 'দাজাজ মান্দি', 'Chicken + Rice + Batata Maklia + Salad + Sauce + Dakus', 750, 1500, 'dajaj-mandi'),
                    self::mandi(40, 'Laham Mandi', 'লাহাম মান্দি', 'Mutton + Rice + Batata Maklia + Salad + Sauce + Dakus', 2100, 4000, 'laham-mandi'),
                    self::mandi(41, 'Al Faham Sada', 'আল ফাহাম সাদা', 'Chicken + Batata Maklia + Dakus Sauce + Thum Sauce', 500, 950, 'al-faham-sada'),
                    self::mandi(42, 'Al Faham Wajba', 'আল ফাহাম ওয়াজবা', 'Chicken + Rice + Batata Maklia + Salad + Dakus Sauce + Thum Sauce', 900, 1700, 'al-faham-wajba'),
                    self::item(43, 'Ruz Bil Halib (4 Cup)', 'রুজ বিল হালিব (৪ কাপ)', 500, null, true, 'Rice with Milk. Pre-order required.'),
                    self::item(44, 'Harish (Per Person)', 'হারিশ (জনপ্রতি)', 250, null, false, 'Pre-order required.'),
                ],
            ],
            [
                'name' => 'Doi & Desserts',
                'name_bn' => 'দই ও মিষ্টান্ন',
                'items' => [
                    self::item(45, 'Premium Tok Misti Doi (per KG)', 'প্রিমিয়াম টক মিষ্টি দই (প্রতি কেজি)', 400, 'tok-misti-doi', true, 'Pre-order required.'),
                ],
            ],
            [
                'name' => 'Morning Breakfast',
                'name_bn' => 'সকালের নাস্তা',
                'seed_once' => true,
                'items' => [
                    self::draft(46, 'Parota', 'পরোটা'),
                    self::draft(47, 'Pech Porota', 'প্যাঁচ পরোটা'),
                    self::draft(48, 'Bhaji', 'ভাজি'),
                    self::draft(49, 'Dal', 'ডাল'),
                    self::draft(50, 'One Curry Item', 'ওয়ান কারি আইটেম', 'Chicken Jhalfrezi, Chicken Soup or Nehari.'),
                    self::draft(51, 'Halua', 'হালুয়া'),
                    self::draft(52, 'Dim Bhaja', 'ডিম ভাজা'),
                    self::draft(53, 'Singara', 'সিঙ্গারা'),
                    self::draft(54, 'Samucha', 'সমুচা'),
                    self::draft(55, 'Cha & Coffee', 'চা ও কফি'),
                ],
            ],
        ];
    }

    /**
     * Interior, exterior and dish photographs for the website gallery.
     *
     * @return array<int, array{file: string, title: string, title_bn: string}>
     */
    public static function gallery(): array
    {
        return [
            ['file' => 'exterior-night-front', 'title' => 'Our signboard at night', 'title_bn' => 'রাতে আমাদের সাইনবোর্ড'],
            ['file' => 'exterior-signboard', 'title' => "Arman's Kitchen signboard", 'title_bn' => 'আরমানস কিচেন সাইনবোর্ড'],
            ['file' => 'exterior-entrance', 'title' => 'Entrance', 'title_bn' => 'প্রবেশপথ'],
            ['file' => 'exterior-storefront', 'title' => 'Storefront', 'title_bn' => 'সামনের দৃশ্য'],
            ['file' => 'interior-lounge', 'title' => 'Dining lounge', 'title_bn' => 'ডাইনিং লাউঞ্জ'],
            ['file' => 'interior-dining-hall', 'title' => 'Dining hall', 'title_bn' => 'ডাইনিং হল'],
            ['file' => 'interior-booth-seating', 'title' => 'Booth seating', 'title_bn' => 'বুথ সিটিং'],
            ['file' => 'interior-corner-seating', 'title' => 'Corner seating', 'title_bn' => 'কর্নার সিটিং'],
            ['file' => 'interior-pendant-lights', 'title' => 'Bottle pendant lights', 'title_bn' => 'বোতল পেন্ডেন্ট লাইট'],
            ['file' => 'interior-wall-lights', 'title' => 'Ambient lighting', 'title_bn' => 'আলো-ছায়ার পরিবেশ'],
            ['file' => 'interior-bar-counter', 'title' => 'Counter and bar', 'title_bn' => 'কাউন্টার ও বার'],
            ['file' => 'interior-table-set', 'title' => 'Table setting', 'title_bn' => 'টেবিল সেটিং'],
            ['file' => 'platter-al-faham', 'title' => 'Al Faham platter', 'title_bn' => 'আল ফাহাম প্লাটার'],
            ['file' => 'dish-doi-fuchka-closeup', 'title' => 'Doi Fuchka', 'title_bn' => 'দই ফুচকা'],
            ['file' => 'dish-rajkachuri', 'title' => 'AK Special Rajkachuri', 'title_bn' => 'এ কে স্পেশাল রাজকচুরি'],
            ['file' => 'drink-pineapple-juice', 'title' => 'Pineapple juice', 'title_bn' => 'আনারসের জুস'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(int $number, string $name, string $nameBn, int $price, ?string $photo = null, bool $veg = false, ?string $description = null): array
    {
        return [
            'number' => $number,
            'name' => $name,
            'name_bn' => $nameBn,
            'price' => $price,
            'photo' => $photo,
            'veg' => $veg,
            'available' => true,
            'description' => $description,
            'variants' => [],
        ];
    }

    /**
     * A dish on the card with no printed price yet. It is created unavailable
     * at 0 so it shows in the admin panel ready to price; the seeder never
     * touches it again once created.
     *
     * @return array<string, mixed>
     */
    private static function draft(int $number, string $name, string $nameBn, ?string $description = null): array
    {
        return [...self::item($number, $name, $nameBn, 0, null, false, $description), 'available' => false];
    }

    /**
     * A mandi dish sold as 1:2 and 1:4 portions. The 1:4 portion comes with zabadi.
     *
     * @return array<string, mixed>
     */
    private static function mandi(int $number, string $name, string $nameBn, string $contents, int $price12, int $price14, string $photo): array
    {
        return [
            ...self::item($number, $name, $nameBn, $price12, $photo, false, $contents.'. The 1:4 portion comes with Zabadi. Pre-order required.'),
            'variants' => [
                ['name' => '1:2', 'name_bn' => '১:২', 'price' => $price12, 'default' => true],
                ['name' => '1:4', 'name_bn' => '১:৪', 'price' => $price14, 'default' => false],
            ],
        ];
    }
}
