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
        $rows = [
            ['exterior-night-front', 'Our signboard at night', 'রাতে আমাদের সাইনবোর্ড'],
            ['exterior-signboard', "Arman's Kitchen signboard", 'আরমানস কিচেন সাইনবোর্ড'],
            ['exterior-entrance', 'Entrance', 'প্রবেশপথ'],
            ['exterior-storefront', 'Storefront', 'সামনের দৃশ্য'],
            ['exterior-night-street', 'On the street at night', 'রাতের রাস্তায়'],
            ['interior-lounge', 'Dining lounge', 'ডাইনিং লাউঞ্জ'],
            ['interior-dining-hall', 'Dining hall', 'ডাইনিং হল'],
            ['interior-booth-seating', 'Booth seating', 'বুথ সিটিং'],
            ['interior-corner-seating', 'Corner seating', 'কর্নার সিটিং'],
            ['interior-quiet-corner', 'A quiet corner', 'নিরিবিলি কোণ'],
            ['interior-pendant-lights', 'Bottle pendant lights', 'বোতল পেন্ডেন্ট লাইট'],
            ['interior-wall-lights', 'Ambient lighting', 'আলো-ছায়ার পরিবেশ'],
            ['interior-bar-counter', 'Counter and bar', 'কাউন্টার ও বার'],
            ['interior-table-set', 'Table setting', 'টেবিল সেটিং'],
            ['interior-glass-door', 'Frosted glass door', 'কাচের দরজা'],
            ['platter-al-faham', 'Al Faham platter', 'আল ফাহাম প্লাটার'],
            ['dish-al-faham-grill', 'Al Faham with rice and salad', 'ভাত ও সালাদসহ আল ফাহাম'],
            ['dish-doi-fuchka-closeup', 'Doi Fuchka', 'দই ফুচকা'],
            ['dish-rajkachuri', 'AK Special Rajkachuri', 'এ কে স্পেশাল রাজকচুরি'],
            ['dish-shahi-chotpoti-bowl', 'Shahi Chotpoti', 'শাহী চটপটি'],
            ['dish-chotpoti-art', 'Chotpoti, spiced', 'মশলাদার চটপটি'],
            ['dish-wedges-platter', 'Spiced potato wedges', 'মশলাদার পটেটো ওয়েজেস'],
            ['dish-wood-board-fry', 'Fried bites on a wooden board', 'কাঠের বোর্ডে ভাজা আইটেম'],
            ['dish-bbq-wings-box', 'BBQ Chicken Wings', 'বিবিকিউ চিকেন উইংস'],
            ['dish-noodles-foil', 'Noodles to take away', 'টেকঅ্যাওয়ে নুডলস'],
            ['dish-rice-bowl-chicken', 'Rice bowl with chicken', 'চিকেনসহ রাইস বোল'],
            ['dish-polao-egg', 'Polao with egg', 'ডিমসহ পোলাও'],
            ['dish-rice-and-curry', 'Rice and curry', 'ভাত ও তরকারি'],
            ['dish-burger-and-fries', 'Burger and fries', 'বার্গার ও ফ্রাই'],
            ['dish-food-boxes', 'Orders packed for delivery', 'ডেলিভারির জন্য প্যাক করা অর্ডার'],
            ['drink-green-lemonade', 'Green Lemonade', 'গ্রিন লেমনেড'],
            ['drink-pineapple-juice', 'Pineapple juice', 'আনারসের জুস'],
            ['drink-coconut-malai', 'Coconut malai juice', 'নারকেল মালাই জুস'],
            ['drink-milk-shakes', 'Milk shakes', 'মিল্ক শেক'],
        ];

        return array_map(fn (array $row): array => ['file' => $row[0], 'title' => $row[1], 'title_bn' => $row[2]], $rows);
    }

    /**
     * Extra photographs of a dish, from menu/extra/{photo}-2.webp onwards.
     *
     * @return list<string> storage-relative source files under the assets folder
     */
    public static function extraPhotos(string $photo): array
    {
        $files = glob(base_path('database/seeders/assets/armans-kitchen/menu/extra/'.$photo.'-*.webp')) ?: [];
        sort($files);

        return array_map(fn (string $file): string => 'menu/extra/'.basename($file), $files);
    }

    /**
     * Journal posts about the real dishes, offers and rooms. Prices and times
     * come from the printed cards and posters.
     *
     * @return array<int, array<string, string>>
     */
    public static function posts(): array
    {
        return [
            [
                'slug' => 'qasr-al-mandi-pre-order',
                'cover' => 'cover-al-faham',
                'category' => 'Menu',
                'title' => 'Qasr Al Mandi: Kabsa, Dajaj Mandi, Laham Mandi and Al Faham on pre-order',
                'title_bn' => 'কাসর আল মান্দি: কাবসা, দাজাজ মান্দি, লাহাম মান্দি ও আল ফাহাম প্রি-অর্ডারে',
                'excerpt' => 'Our Qasr Al Mandi range is cooked on pre-order, in 1:2 and 1:4 portions. Here is what each one comes with and what it costs.',
                'excerpt_bn' => 'আমাদের কাসর আল মান্দি প্রি-অর্ডারে রান্না হয়, ১:২ ও ১:৪ পরিমাণে। কোনটায় কী থাকে আর দাম কত, এখানে দেখুন।',
                'body' => 'Qasr Al Mandi is the part of our menu that we cook only on pre-order, so every plate is made for the table that asked for it.

**Kabsa** comes with chicken, rice, batata maklia, salad, Dakus sauce and Thum sauce. The 1:2 portion is Tk 900 and the 1:4 portion, which also comes with Zabadi, is Tk 1,700.

**Dajaj Mandi** is chicken, rice, batata maklia, salad, sauce and Dakus: Tk 750 for 1:2 and Tk 1,500 for 1:4 with Zabadi.

**Laham Mandi** is the mutton version, served the same way: Tk 2,100 for 1:2 and Tk 4,000 for 1:4 with Zabadi.

**Al Faham Sada** is grilled chicken with batata maklia, Dakus sauce and Thum sauce: Tk 500 for 1:2 and Tk 950 for 1:4. **Al Faham Wajba** adds rice and salad: Tk 900 and Tk 1,700.

For dessert there is Ruz Bil Halib, rice with milk, at Tk 500 for four cups, and Harish at Tk 250 per person.

Pre-order by calling 01777656561, or order through Pathao Food, Foodpanda or Foodi.',
                'body_bn' => 'কাসর আল মান্দি আমাদের মেনুর সেই অংশ যা শুধু প্রি-অর্ডারে রান্না হয়, তাই প্রতিটি প্লেট যে টেবিল চেয়েছে তার জন্যই তৈরি।

**কাবসা**: চিকেন, ভাত, বাতাতা মাকলিয়া, সালাদ, দাকুস সস ও থুম সস। ১:২ পরিমাণ ৯০০ টাকা, জাবাদিসহ ১:৪ পরিমাণ ১,৭০০ টাকা।

**দাজাজ মান্দি**: ১:২ পরিমাণ ৭৫০ টাকা, জাবাদিসহ ১:৪ পরিমাণ ১,৫০০ টাকা।

**লাহাম মান্দি** (খাসির মাংস): ১:২ পরিমাণ ২,১০০ টাকা, জাবাদিসহ ১:৪ পরিমাণ ৪,০০০ টাকা।

**আল ফাহাম সাদা**: ১:২ পরিমাণ ৫০০ টাকা, ১:৪ পরিমাণ ৯৫০ টাকা। **আল ফাহাম ওয়াজবা** (ভাত ও সালাদসহ): ৯০০ ও ১,৭০০ টাকা।

মিষ্টিতে রয়েছে রুজ বিল হালিব (৪ কাপ ৫০০ টাকা) ও হারিশ (জনপ্রতি ২৫০ টাকা)।

প্রি-অর্ডারের জন্য ০১৭৭৭৬৫৬৫৬১ নম্বরে কল করুন, অথবা পাঠাও ফুড, ফুডপান্ডা বা ফুডিতে অর্ডার করুন।',
                'days_ago' => '3',
            ],
            [
                'slug' => 'morning-breakfast-from-27-october',
                'cover' => 'cover-dining-hall',
                'category' => 'News',
                'title' => 'Morning breakfast starts from 27 October, 8:00 AM',
                'title_bn' => 'সকালের নাস্তা শুরু ২৭ অক্টোবর থেকে, সকাল ৮টায়',
                'excerpt' => 'From 27 October we open at 8:00 AM with a breakfast menu: parota, bhaji, dal, halua, dim bhaja, singara, samucha and tea or coffee.',
                'excerpt_bn' => '২৭ অক্টোবর থেকে সকাল ৮টায় খুলছি নাস্তার মেনু নিয়ে: পরোটা, ভাজি, ডাল, হালুয়া, ডিম ভাজা, সিঙ্গারা, সমুচা এবং চা বা কফি।',
                'body' => "We are adding mornings to Arman's Kitchen. From 27 October, breakfast is served from 8:00 AM.

The breakfast menu has ten choices: parota, pech porota, bhaji, dal, one curry item of the day (chicken jhalfrezi, chicken soup or nehari), halua, dim bhaja, singara, samucha, and tea or coffee.

Come in before work, bring the family on a weekend morning, or order for delivery. Questions or group bookings: 01777656561.

We are at House 133, Road No. 06, Mohammadia Housing Society, Mohammadpur, Dhaka.",
                'body_bn' => 'আরমানস কিচেনে এবার সকালও যুক্ত হচ্ছে। ২৭ অক্টোবর থেকে সকাল ৮টা থেকে নাস্তা পাওয়া যাবে।

নাস্তার মেনুতে আছে: পরোটা, প্যাঁচ পরোটা, ভাজি, ডাল, দিনের একটি কারি আইটেম (চিকেন ঝালফ্রেজি, চিকেন স্যুপ বা নেহারি), হালুয়া, ডিম ভাজা, সিঙ্গারা, সমুচা এবং চা বা কফি।

অফিসের আগে আসুন, ছুটির সকালে পরিবার নিয়ে আসুন, অথবা ডেলিভারিতে অর্ডার করুন। যোগাযোগ: ০১৭৭৭৬৫৬৫৬১।

ঠিকানা: বাসা ১৩৩, রোড ০৬, মোহাম্মদিয়া হাউজিং সোসাইটি, মোহাম্মদপুর, ঢাকা।',
                'days_ago' => '1',
            ],
            [
                'slug' => 'free-fuchka-with-monstar-burger',
                'cover' => 'promo-free-fuchka-with-burger',
                'category' => 'Offers',
                'title' => 'Buy a Monstar Burger, get a plate of fuchka free',
                'title_bn' => 'মনস্টার বার্গার কিনলেই এক প্লেট ফুচকা ফ্রি',
                'excerpt' => 'Order a Monstar Burger and a plate of fuchka comes free. Call 01777656561 or order on Pathao Food, Foodi and Foodpanda.',
                'excerpt_bn' => 'মনস্টার বার্গার কিনলেই এক প্লেট ফুচকা সম্পূর্ণ ফ্রি। কল করুন ০১৭৭৭৬৫৬৫৬১ নম্বরে, অথবা পাঠাও ফুড, ফুডি ও ফুডপান্ডায় অর্ডার করুন।',
                'body' => 'Our offer poster says it simply: buy a Monstar Burger and a plate of fuchka is free.

The Monstar Burger is a thick beef patty with cheese, onion and fries on the side. The fuchka is made the way we make it every day, crisp shells with the tangy filling.

Terms apply, so please ask when you order. Call 01777656561, or order on Pathao Food, Foodi or Foodpanda.',
                'body_bn' => 'আমাদের অফার পোস্টারে কথাটা সহজ: মনস্টার বার্গার কিনলেই এক প্লেট ফুচকা ফ্রি।

মনস্টার বার্গারে থাকে মোটা বিফ প্যাটি, চিজ, পেঁয়াজ আর পাশে ফ্রাই। ফুচকা বানানো হয় প্রতিদিনের মতোই, মচমচে খোসায় ঝাল-টক পুর।

শর্ত প্রযোজ্য, তাই অর্ডারের সময় জেনে নিন। কল করুন ০১৭৭৭৬৫৬৫৬১ নম্বরে, অথবা পাঠাও ফুড, ফুডি বা ফুডপান্ডায় অর্ডার করুন।',
                'days_ago' => '7',
            ],
            [
                'slug' => 'free-burger-festival',
                'cover' => 'promo-free-burger-festival',
                'category' => 'Offers',
                'title' => 'Free Burger Festival',
                'title_bn' => 'সম্পূর্ণ ফ্রি বার্গার ফেস্টিভ্যাল',
                'excerpt' => 'Our Free Burger Festival poster, with the details of how to join in. Call 01777656561.',
                'excerpt_bn' => 'আমাদের ফ্রি বার্গার ফেস্টিভ্যালের পোস্টার ও অংশ নেওয়ার বিস্তারিত। কল করুন ০১৭৭৭৬৫৬৫৬১।',
                'body' => 'We ran a Free Burger Festival, and this is the poster that went with it.

The offer was for a limited time and for a limited number of guests, so the terms are printed on the poster itself. If you see us run a festival again, call 01777656561 or ask at the counter before you come.

Our chicken burgers start at Tk 70 for the Student Burger and go up to the Cheesy Jumbo Burger at Tk 300.',
                'body_bn' => 'আমরা একটি ফ্রি বার্গার ফেস্টিভ্যাল করেছিলাম, এটি তার পোস্টার।

অফারটি সীমিত সময় ও সীমিত সংখ্যক অতিথির জন্য ছিল, তাই শর্ত পোস্টারেই ছাপা আছে। আবার ফেস্টিভ্যাল হলে আসার আগে ০১৭৭৭৬৫৬৫৬১ নম্বরে কল করুন বা কাউন্টারে জেনে নিন।

আমাদের চিকেন বার্গার শুরু স্টুডেন্ট বার্গার ৭০ টাকা থেকে, চিজি জাম্বো বার্গার ৩০০ টাকা পর্যন্ত।',
                'days_ago' => '20',
            ],
            [
                'slug' => 'green-lemonade-for-100-taka',
                'cover' => 'promo-green-lemonade',
                'category' => 'Drinks',
                'title' => 'Green Lemonade, fresh and cool, for Tk 100',
                'title_bn' => 'গ্রিন লেমনেড, ফ্রেশ ও ঠান্ডা, মাত্র ১০০ টাকা',
                'excerpt' => 'Our Green Lemonade is Tk 100. It is on the menu for dine-in and on Pathao Food, Foodi and Foodpanda.',
                'excerpt_bn' => 'আমাদের গ্রিন লেমনেড মাত্র ১০০ টাকা। ডাইন-ইনে এবং পাঠাও ফুড, ফুডি ও ফুডপান্ডায় পাওয়া যায়।',
                'body' => 'Green Lemonade is fresh and cool, and it is only Tk 100.

We also keep other drinks on the card: Rong Cha at Tk 10, Masala Rong Cha at Tk 20, Dudh Cha at Tk 30, regular coffee at Tk 40, Lassi, Cold Coffee and Chocolate Milk Shake at Tk 100 each.

Call 01777656561 to order.',
                'body_bn' => 'গ্রিন লেমনেড ফ্রেশ ও ঠান্ডা, দাম মাত্র ১০০ টাকা।

কার্ডে আরও ড্রিংকস আছে: রং চা ১০ টাকা, মসলা রং চা ২০ টাকা, দুধ চা ৩০ টাকা, রেগুলার কফি ৪০ টাকা, লাচ্ছি, কোল্ড কফি ও চকলেট মিল্ক শেক ১০০ টাকা করে।

অর্ডারের জন্য কল করুন ০১৭৭৭৬৫৬৫৬১।',
                'days_ago' => '12',
            ],
            [
                'slug' => 'street-food-ak-special',
                'cover' => 'cover-street-food',
                'category' => 'Menu',
                'title' => 'Street food, the AK Special way: Chotpoti, Churmur and Doi Fuchka',
                'title_bn' => 'এ কে স্পেশাল স্ট্রিট ফুড: চটপটি, চুরমুর ও দই ফুচকা',
                'excerpt' => 'Regular Chotpoti is Tk 70 and Shahi Chotpoti, Churmur and Doi Fuchka are our AK Specials.',
                'excerpt_bn' => 'রেগুলার চটপটি ৭০ টাকা, আর শাহী চটপটি, চুরমুর ও দই ফুচকা আমাদের এ কে স্পেশাল।',
                'body' => 'Street food is where many of our regulars start.

Regular Chotpoti is Tk 70 and Regular Fuchka is Tk 80. The AK Specials are Shahi Chotpoti at Tk 100, Churmur at Tk 100 and Doi Fuchka at Tk 140.

The Shahi Chotpoti is topped with nuts, raisins and crisp fried onion, and the Doi Fuchka is covered in yogurt and sev.

Order for dine-in or delivery on 01777656561.',
                'body_bn' => 'স্ট্রিট ফুড দিয়েই আমাদের অনেক নিয়মিত অতিথি শুরু করেন।

রেগুলার চটপটি ৭০ টাকা, রেগুলার ফুচকা ৮০ টাকা। এ কে স্পেশাল হলো শাহী চটপটি ১০০ টাকা, চুরমুর ১০০ টাকা ও দই ফুচকা ১৪০ টাকা।

শাহী চটপটির ওপরে থাকে বাদাম, কিশমিশ ও মচমচে পেঁয়াজ ভাজা, আর দই ফুচকা ঢাকা থাকে দই ও সেভে।

ডাইন-ইন বা ডেলিভারির জন্য কল করুন ০১৭৭৭৬৫৬৫৬১।',
                'days_ago' => '15',
            ],
            [
                'slug' => 'tok-misti-doi-and-homemade-doi',
                'cover' => 'promo-tok-misti-doi',
                'category' => 'Menu',
                'title' => 'Premium Tok Misti Doi and home-made doi, on pre-order',
                'title_bn' => 'প্রিমিয়াম টক মিষ্টি দই ও হোম মেড দই, প্রি-অর্ডারে',
                'excerpt' => 'Premium Tok Misti Doi is Tk 400 per KG on pre-order. Call 01777656561 or 01777656562.',
                'excerpt_bn' => 'প্রিমিয়াম টক মিষ্টি দই প্রতি কেজি ৪০০ টাকা, প্রি-অর্ডারে। কল করুন ০১৭৭৭৬৫৬৫৬১ বা ০১৭৭৭৬৫৬৫৬২।',
                'body' => 'Premium Tok Misti Doi is Tk 400 per KG, and pre-order is required.

We also make home-made doi. For orders and prices of the home-made doi, call 01777656561 or 01777656562.

You can order on Pathao Food, Foodi and Foodpanda too.',
                'body_bn' => 'প্রিমিয়াম টক মিষ্টি দই প্রতি কেজি ৪০০ টাকা, প্রি-অর্ডার বাধ্যতামূলক।

আমরা হোম মেড দইও বানাই। হোম মেড দইয়ের অর্ডার ও দামের জন্য কল করুন ০১৭৭৭৬৫৬৫৬১ বা ০১৭৭৭৬৫৬৫৬২ নম্বরে।

পাঠাও ফুড, ফুডি ও ফুডপান্ডাতেও অর্ডার করতে পারেন।',
                'days_ago' => '10',
            ],
            [
                'slug' => 'visit-us-in-mohammadpur',
                'cover' => 'cover-signboard-night',
                'category' => 'News',
                'title' => "Visit Arman's Kitchen in Mohammadpur",
                'title_bn' => 'মোহাম্মদপুরে আরমানস কিচেনে আসুন',
                'excerpt' => 'House 133, Road No. 06, Mohammadia Housing Society, Mohammadpur, Dhaka-1207. Call 01777656561.',
                'excerpt_bn' => 'বাসা ১৩৩, রোড ০৬, মোহাম্মদিয়া হাউজিং সোসাইটি, মোহাম্মদপুর, ঢাকা-১২০৭। কল করুন ০১৭৭৭৬৫৬৫৬১।',
                'body' => "Arman's Kitchen has been serving since 2013.

You will find us at House 133, Road No. 06, Mohammadia Housing Society, Mohammadpur, Dhaka-1207. Look for the lit signboard at the entrance.

Inside there is booth seating, a quiet corner by the glass wall and a counter with a bar of coloured bottle lights. Call 01777656561 for any inquiry, or follow Arman's Kitchen on Facebook.",
                'body_bn' => 'আরমানস কিচেন ২০১৩ সাল থেকে সেবা দিচ্ছে।

আমাদের পাবেন বাসা ১৩৩, রোড ০৬, মোহাম্মদিয়া হাউজিং সোসাইটি, মোহাম্মদপুর, ঢাকা-১২০৭ ঠিকানায়। প্রবেশপথে আলো জ্বলা সাইনবোর্ড দেখে চিনে নিন।

ভেতরে আছে বুথ সিটিং, কাচের দেয়ালের পাশে নিরিবিলি কোণ আর রঙিন বোতলের লাইট দেওয়া কাউন্টার। যেকোনো প্রশ্নে কল করুন ০১৭৭৭৬৫৬৫৬১ নম্বরে, অথবা ফেসবুকে আরমানস কিচেন ফলো করুন।',
                'days_ago' => '30',
            ],
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
