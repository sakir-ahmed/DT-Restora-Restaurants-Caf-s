<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * The demo menu for every outlet. Prices are Gulshan list prices in BDT.
 * Other branches apply their own factor when seeding.
 *
 * Each raw item is:
 * [name, name_bn, price, vegetarian, photo, description, description_bn, variants, addons, sold_out]
 * A variant is [name, name_bn, price, is_default]. An addon is [name, name_bn, price].
 */
final class DemoMenuCatalog
{
    /**
     * @return list<array{name: string, name_bn: string, items: list<array<string, mixed>>}>
     */
    public static function categories(): array
    {
        $categories = [];

        foreach (self::raw() as $category) {
            $categories[] = [
                'name' => $category[0],
                'name_bn' => $category[1],
                'items' => array_map(fn (array $item): array => self::item($item), $category[2]),
            ];
        }

        return $categories;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    private static function item(array $row): array
    {
        $variants = [];

        foreach ($row[7] ?? [] as $variant) {
            $variants[] = [
                'name' => $variant[0],
                'name_bn' => $variant[1],
                'price' => $variant[2],
                'default' => (bool) ($variant[3] ?? false),
            ];
        }

        $addOns = [];

        foreach ($row[8] ?? [] as $addOn) {
            $addOns[] = [
                'name' => $addOn[0],
                'name_bn' => $addOn[1],
                'price' => $addOn[2],
            ];
        }

        return [
            'name' => $row[0],
            'name_bn' => $row[1],
            'price' => $row[2],
            'veg' => (bool) $row[3],
            'photo' => $row[4],
            'description' => $row[5],
            'description_bn' => $row[6],
            'variants' => $variants,
            'addons' => $addOns,
            'sold_out' => (bool) ($row[9] ?? false),
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: list<array<int, mixed>>}>
     */
    private static function raw(): array
    {
        $size = fn (int $regular, int $large): array => [
            ['Regular', 'রেগুলার', $regular, true],
            ['Large', 'লার্জ', $large, false],
        ];

        $riceSide = [
            ['Plain Rice', 'সাদা ভাত', 40],
            ['Naan', 'নান', 40],
            ['Egg', 'ডিম', 30],
        ];

        $burgerSide = [
            ['Cheese Slice', 'চিজ স্লাইস', 40],
            ['Fries Regular', 'ফ্রাই রেগুলার', 70],
            ['Extra Patty', 'অতিরিক্ত প্যাটি', 90],
        ];

        return [
            ['Kacchi & Biryani', 'কাচ্চি ও বিরিয়ানি', [
                ['Kacchi Biryani', 'কাচ্চি বিরিয়ানি', 560, false, 'biryani', 'Mutton and aged rice, sealed and slow-cooked in a handi.', 'হাঁড়িতে দমে রান্না মাটন কাচ্চি, পুরনো চালে।', $size(560, 820), [['Borhani', 'বোরহানি', 50], ['Egg', 'ডিম', 30], ['Extra Roast', 'অতিরিক্ত রোস্ট', 120]]],
                ['Chicken Biryani', 'চিকেন বিরিয়ানি', 340, false, 'chicken-biryani', 'Fragrant basmati, saffron and a whole spice-braised chicken piece.', 'বাসমতী, জাফরান আর মসলাদার চিকেনের টুকরো।', $size(340, 460), [['Borhani', 'বোরহানি', 50], ['Egg', 'ডিম', 30]]],
                ['Beef Tehari', 'বিফ তেহারি', 280, false, 'tehari', 'Dhaka-style beef tehari with potato and green chilli.', 'আলুর টুকরো আর কাঁচা মরিচ দিয়ে ঢাকার বিফ তেহারি।', $size(280, 380), [['Egg', 'ডিম', 30]]],
                ['Mutton Kacchi', 'মাটন কাচ্চি', 680, false, 'biryani', 'A fuller pot of kacchi for the table, served with salad.', 'সালাদসহ পুরো হাঁড়ির মাটন কাচ্চি।', [['Half', 'হাফ', 680, true], ['Full', 'ফুল', 1280, false]], [['Borhani', 'বোরহানি', 50]]],
                ['Morog Polao', 'মোরগ পোলাও', 380, false, 'polao', 'Mild chicken polao, the Friday lunch plate.', 'হালকা মসলার মোরগ পোলাও, শুক্রবারের দুপুরের প্লেট।', $size(380, 520), $riceSide],
                ['Vegetable Biryani', 'ভেজিটেবল বিরিয়ানি', 260, true, 'rice', 'Seasonal vegetables and basmati, no meat stock.', 'মৌসুমি সবজি আর বাসমতী, মাংসের ঝোল ছাড়া।', $size(260, 340), [['Raita', 'রায়তা', 30]]],
                ['Bhuna Khichuri', 'ভুনা খিচুড়ি', 240, true, 'khichuri', 'Slow-bhuna moong khichuri with ghee and green chilli.', 'ঘি আর কাঁচা মরিচ দিয়ে ভুনা মুগের খিচুড়ি।', $size(240, 320), [['Egg', 'ডিম', 30], ['Beef Bhuna', 'বিফ ভুনা', 120]]],
                ['Egg Fried Rice', 'ডিম ফ্রাইড রাইস', 180, false, 'fried-rice', 'Wok-fried rice with egg, spring onion and soy.', 'ডিম, স্প্রিং অনিয়ন আর সয়ায় ভাজা ভাত।', [], [['Chicken', 'চিকেন', 60]]],
                ['Chicken Fried Rice', 'চিকেন ফ্রাইড রাইস', 240, false, 'fried-rice', 'The same wok rice with sliced grilled chicken.', 'গ্রিলড চিকেনের স্লাইসসহ ফ্রাইড রাইস।', [], [['Egg', 'ডিম', 30]]],
            ]],
            ['Curry & Rice', 'তরকারি ও ভাত', [
                ['Beef Bhuna', 'বিফ ভুনা', 380, false, 'beef-bhuna', 'Dry-fried beef with onion, garlic and a dark gravy.', 'পেঁয়াজ রসুনে শুকনো কষা বিফ ভুনা।', [], $riceSide],
                ['Chicken Roast', 'চিকেন রোস্ট', 340, false, 'chicken-roast', 'Yogurt-marinated roast chicken, sliced to order.', 'দইয়ে ম্যারিনেট রোস্ট চিকেন, অর্ডারে কাটা হয়।', [['Quarter', 'কোয়ার্টার', 340, true], ['Half', 'হাফ', 620, false]], $riceSide],
                ['Chingri Malai Curry', 'চিংড়ি মালাইকারি', 460, false, 'chingri', 'Prawns in a light coconut and mustard-oil gravy.', 'নারকেল দুধ আর সরিষার তেলে হালকা চিংড়ি মালাইকারি।', [], $riceSide],
                ['Mutton Rezala', 'মাটন রেজালা', 480, false, 'butter-chicken', 'Pale, yogurt-rich rezala with whole garam masala.', 'দইয়ের হালকা রেজালা, গোটা গরম মসলাসহ।', [], $riceSide],
                ['Chicken Korma', 'চিকেন কোরমা', 340, false, 'chicken-curry', 'A mild korma finished with cream and fried onion.', 'ক্রিম আর বেরেস্তা দিয়ে হালকা চিকেন কোরমা।', [], $riceSide],
                ['Butter Chicken', 'বাটার চিকেন', 390, false, 'butter-chicken', 'Tandoor chicken in a tomato-butter sauce.', 'টমেটো-বাটার সসে তন্দুরি চিকেন।', [], [['Naan', 'নান', 40], ['Rice', 'ভাত', 40]]],
                ['Kala Bhuna', 'কালা ভুনা', 420, false, 'kala-bhuna', 'Chittagong dark bhuna, almost no gravy, very peppery.', 'চট্টগ্রামের কালা ভুনা, ঝোল ছাড়া, ঝাল বেশি।', [], $riceSide],
                ['Mezbani Beef', 'মেজবানি গোশত', 400, false, 'mezbani', 'Festive mezban beef, slow cooked with bone.', 'হাড়ে রান্না মেজবানি গোশত।', [], $riceSide],
                ['Dal Bhuna', 'ডাল ভুনা', 90, true, 'dal', 'Moong dal fried with garlic, chilli and ghee.', 'রসুন, মরিচ আর ঘিয়ে ভুনা মুগের ডাল।', [], [['Rice', 'ভাত', 40]]],
                ['Plain Rice', 'সাদা ভাত', 60, true, 'rice', 'Steamed kalijira rice.', 'সেদ্ধ কালিজিরা চাল।', [['Single', 'সিঙ্গেল', 60, true], ['Double', 'ডাবল', 100, false]], []],
                ['Shorshe Ilish', 'সর্ষে ইলিশ', 520, false, 'ilish', 'Hilsa in a sharp mustard paste. When the market has it.', 'শর্ষে বাটায় ইলিশ। বাজারে পেলেই।', [], $riceSide, true],
                ['Bangla Lunch Thali', 'বাংলা লাঞ্চ থালি', 320, false, 'thali', 'Rice, dal, one curry, bhaji and salad on a steel plate.', 'ভাত, ডাল, একটা তরকারি, ভাজি আর সালাদ।', [], [['Extra Curry', 'অতিরিক্ত তরকারি', 80]]],
            ]],
            ['Kebabs & Tandoor', 'কাবাব ও তন্দুর', [
                ['Chicken Tikka', 'চিকেন টিক্কা', 280, false, 'tandoori', 'Boneless tikka from the tandoor, with mint sauce.', 'তন্দুরের বোনলেস টিক্কা, পুদিনা সসসহ।', [['4 pcs', '৪ পিস', 280, true], ['8 pcs', '৮ পিস', 520, false]], [['Naan', 'নান', 40]]],
                ['Seekh Kabab', 'সিক কাবাব', 260, false, 'seekh', 'Minced beef seekh, charcoal finished.', 'কয়লায় শেষ করা গরুর কিমার সিক কাবাব।', [['2 pcs', '২ পিস', 260, true], ['4 pcs', '৪ পিস', 480, false]], [['Naan', 'নান', 40], ['Raita', 'রায়তা', 30]]],
                ['Shami Kabab', 'শামি কাবাব', 180, false, 'shami', 'Pan-fried shami with onion and green chilli.', 'পেঁয়াজ আর কাঁচা মরিচ দিয়ে ভাজা শামি কাবাব।', [['2 pcs', '২ পিস', 180, true], ['4 pcs', '৪ পিস', 320, false]], []],
                ['Boti Kabab', 'বটি কাবাব', 340, false, 'grill', 'Bone-in beef boti, smoky and well done at the edge.', 'হাড়ে বটি কাবাব, কিনারাটা ঝলসানো।', [], [['Naan', 'নান', 40]]],
                ['Grilled Chicken', 'গ্রিলড চিকেন', 420, false, 'grill', 'Half a bird, lemon, chilli and a charred skin.', 'আধা মুরগি, লেবু মরিচ, ঝলসানো চামড়া।', [['Half', 'হাফ', 420, true], ['Full', 'ফুল', 780, false]], [['Fries', 'ফ্রাই', 70]]],
                ['Tandoori Chicken', 'তন্দুরি চিকেন', 390, false, 'tandoori', 'Classic red tandoori, served on a sizzler.', 'ক্লাসিক লাল তন্দুরি, সিজলারে।', [['Quarter', 'কোয়ার্টার', 220, false], ['Half', 'হাফ', 390, true]], [['Naan', 'নান', 40]]],
                ['BBQ Chicken Wings', 'বারবিকিউ চিকেন উইংস', 320, false, 'bbq', 'Glazed wings, sticky and a little sweet.', 'চকচকে, একটু মিষ্টি গ্লেজের উইংস।', [['6 pcs', '৬ পিস', 320, true], ['12 pcs', '১২ পিস', 580, false]], [['Fries', 'ফ্রাই', 70]]],
            ]],
            ['Snacks', 'স্ন্যাকস', [
                ['Chicken Shingara', 'চিকেন সিঙ্গারা', 40, false, 'samosa', 'Crisp triangle with spiced chicken.', 'মসলাদার চিকেনের খাস্তা সিঙ্গারা।', [], [['Sauce', 'সস', 10]]],
                ['Beef Shingara', 'বিফ সিঙ্গারা', 50, false, 'samosa', 'The same pastry with a beef filling.', 'একই খাস্তায় বিফের পুর।', [], [['Sauce', 'সস', 10]]],
                ['Fuchka', 'ফুচকা', 80, true, 'fuchka', 'Eight shells, tamarind water and chickpea.', 'আটটা ফুচকা, তেঁতুলের পানি আর ছোলা।', [['8 pcs', '৮ পিস', 80, true], ['12 pcs', '১২ পিস', 110, false]], []],
                ['Chotpoti', 'চটপটি', 70, true, 'chotpoti', 'Chickpea, egg, potato and tamarind in a cup.', 'ছোলা, ডিম, আলু আর তেঁতুল এক কাপে।', [], [['Extra Egg', 'অতিরিক্ত ডিম', 20]]],
                ['Chicken Chap', 'চিকেন চপ', 90, false, 'chicken-chap', 'Flat spiced chicken chop, breadcrumbed.', 'ব্রেডক্রাম্বে চ্যাপ্টা চিকেন চপ।', [], []],
                ['Dim Chop', 'ডিম চপ', 70, false, 'dim-chop', 'Boiled egg in a potato crust.', 'আলুর খোসায় সেদ্ধ ডিম।', [], []],
                ['Beguni', 'বেগুনি', 40, true, 'beguni', 'Eggplant fritters, hot from the kadhai.', 'কড়াই থেকে গরম বেগুনি।', [], []],
                ['French Fries', 'ফ্রেঞ্চ ফ্রাই', 90, true, 'fries', 'Salted fries. Ask if you want them spiced.', 'লবণ দেওয়া ফ্রাই। মসলা চাইলে বলবেন।', [['Regular', 'রেগুলার', 90, true], ['Large', 'লার্জ', 140, false]], [['Cheese Sauce', 'চিজ সস', 30]]],
                ['Chicken Nuggets', 'চিকেন নাগেটস', 160, false, 'nuggets', 'Six crisp nuggets with a dip.', 'ছয়টা খাস্তা নাগেট, সসসহ।', [['6 pcs', '৬ পিস', 160, true], ['10 pcs', '১০ পিস', 240, false]], []],
                ['Chicken Momos', 'চিকেন মোমো', 180, false, 'momo', 'Steamed chicken momos with a chilli dip.', 'স্টিমড চিকেন মোমো, ঝাল সসসহ।', [['6 pcs', '৬ পিস', 180, true], ['10 pcs', '১০ পিস', 280, false]], []],
            ]],
            ['Burgers', 'বার্গার', [
                ['Chicken Burger', 'চিকেন বার্গার', 220, false, 'burger', 'Crispy chicken, lettuce and the house sauce.', 'ক্রিস্পি চিকেন, লেটুস আর হাউস সস।', [['Single', 'সিঙ্গেল', 220, true], ['Double Patty', 'ডাবল প্যাটি', 320, false]], $burgerSide],
                ['Beef Burger', 'বিফ বার্গার', 280, false, 'burger', 'A smashed beef patty, onion and pickle.', 'স্ম্যাশড বিফ প্যাটি, পেঁয়াজ আর আচার।', [['Single', 'সিঙ্গেল', 280, true], ['Double Patty', 'ডাবল প্যাটি', 400, false]], $burgerSide],
                ['Double Cheese Burger', 'ডাবল চিজ বার্গার', 360, false, 'burger', 'Two beef patties and two slices of cheese.', 'দুই প্যাটি বিফ, দুই স্লাইস চিজ।', [], $burgerSide],
                ['Grilled Chicken Burger', 'গ্রিলড চিকেন বার্গার', 300, false, 'grill', 'Charred chicken breast instead of a fried fillet.', 'ভাজার বদলে ঝলসানো চিকেনের ব্রেস্ট।', [], $burgerSide],
                ['Vegetable Burger', 'ভেজিটেবল বার্গার', 180, true, 'falafel', 'A spiced vegetable patty for a meat-free order.', 'মাংস ছাড়া মসলাদার সবজির প্যাটি।', [], [['Cheese Slice', 'চিজ স্লাইস', 40], ['Fries Regular', 'ফ্রাই রেগুলার', 70]]],
            ]],
            ['Pizza', 'পিজা', [
                ['Margherita Pizza', 'মার্ঘেরিটা পিজা', 420, true, 'pizza', 'Tomato, mozzarella and basil on a thin base.', 'পাতলা বেসে টমেটো, মোজারেলা আর তুলসি।', [['8 inch', '৮ ইঞ্চি', 420, true], ['12 inch', '১২ ইঞ্চি', 680, false]], []],
                ['Chicken Tikka Pizza', 'চিকেন টিক্কা পিজা', 560, false, 'pizza', 'Tikka pieces, onion and a little mint.', 'টিক্কা, পেঁয়াজ আর একটু পুদিনা।', [['8 inch', '৮ ইঞ্চি', 560, true], ['12 inch', '১২ ইঞ্চি', 860, false]], []],
                ['Beef Pepperoni Pizza', 'বিফ পেপেরনি পিজা', 620, false, 'pizza', 'Beef pepperoni and extra cheese.', 'বিফ পেপেরনি আর বাড়তি চিজ।', [['8 inch', '৮ ইঞ্চি', 620, true], ['12 inch', '১২ ইঞ্চি', 940, false]], []],
                ['Mushroom Pizza', 'মাশরুম পিজা', 480, true, 'pizza', 'Roasted mushroom, garlic and mozzarella.', 'ভাজা মাশরুম, রসুন আর মোজারেলা।', [['8 inch', '৮ ইঞ্চি', 480, true], ['12 inch', '১২ ইঞ্চি', 740, false]], []],
                ['Garlic Bread', 'গার্লিক ব্রেড', 160, true, 'bread', 'Toasted baguette with garlic butter.', 'রসুন মাখানো টোস্টেড ব্যাগুয়েট।', [], [['Cheese', 'চিজ', 40]]],
            ]],
            ['Pasta & Noodles', 'পাস্তা ও নুডুলস', [
                ['Pasta Alfredo', 'পাস্তা আলফ্রেডো', 380, true, 'pasta', 'Fettuccine in a parmesan cream sauce.', 'পারমেজান ক্রিম সসে ফেতুচিনি।', [], [['Grilled Chicken', 'গ্রিলড চিকেন', 80]]],
                ['Chicken Pasta', 'চিকেন পাস্তা', 420, false, 'pasta', 'Penne, grilled chicken and a light tomato cream.', 'পেনে, গ্রিলড চিকেন আর হালকা টমেটো ক্রিম।', [], []],
                ['Chow Mein', 'চাউমিন', 240, false, 'noodles', 'Wok noodles with chicken and cabbage.', 'চিকেন আর বাঁধাকপি দিয়ে ওয়কের নুডুলস।', [], [['Egg', 'ডিম', 30]]],
                ['Hakka Noodles', 'হাক্কা নুডুলস', 260, false, 'noodles', 'Drier Hakka noodles, more pepper, more soy.', 'শুকনো হাক্কা নুডুলস, বেশি গোল মরিচ আর সয়া।', [], []],
                ['Chicken Ramen', 'চিকেন রামেন', 340, false, 'ramen', 'A bowl of broth, noodles, egg and chicken.', 'ঝোল, নুডুলস, ডিম আর চিকেনের বাটি।', [], [['Extra Egg', 'অতিরিক্ত ডিম', 30]]],
            ]],
            ['Fried & Wraps', 'ফ্রাইড ও র‍্যাপ', [
                ['Crispy Chicken', 'ক্রিস্পি চিকেন', 280, false, 'fried-chicken', 'Three pieces of crunchy fried chicken.', 'তিন টুকরো খাস্তা ফ্রাইড চিকেন।', [['3 pcs', '৩ পিস', 280, true], ['5 pcs', '৫ পিস', 440, false]], [['Fries', 'ফ্রাই', 70]]],
                ['Hot Wings', 'হট উইংস', 260, false, 'wings', 'A hotter glaze than the BBQ wings.', 'বারবিকিউ উইংসের চেয়ে ঝাল গ্লেজ।', [['6 pcs', '৬ পিস', 260, true], ['12 pcs', '১২ পিস', 480, false]], []],
                ['Chicken Wrap', 'চিকেন র‍্যাপ', 240, false, 'wrap', 'Grilled chicken, salad and sauce in a tortilla.', 'টর্টিলায় গ্রিলড চিকেন, সালাদ আর সস।', [], [['Cheese', 'চিজ', 40]]],
                ['Chicken Shawarma', 'চিকেন শাওয়ারমা', 220, false, 'shawarma', 'Stacked shawarma chicken in pita with garlic sauce.', 'পিটায় শাওয়ারমা চিকেন, রসুনের সসসহ।', [], [['Fries', 'ফ্রাই', 70], ['Cheese', 'চিজ', 40]]],
                ['Falafel Wrap', 'ফালাফেল র‍্যাপ', 200, true, 'falafel', 'Crisp falafel, pickle and tahini.', 'খাস্তা ফালাফেল, আচার আর তাহিনি।', [], []],
            ]],
            ['Seafood', 'সীফুড', [
                ['Grilled Fish', 'গ্রিলড ফিশ', 380, false, 'fish', 'A whole grilled fish with lemon and chilli.', 'লেবু মরিচ দিয়ে আস্ত গ্রিলড মাছ।', [], [['Rice', 'ভাত', 40]]],
                ['Prawn Fry', 'চিংড়ি ভাজা', 360, false, 'shrimp', 'Shell-on prawns, mustard oil and turmeric.', 'খোসাসহ চিংড়ি, সরিষার তেল আর হলুদ।', [], $riceSide],
                ['Fish Finger', 'ফিশ ফিঙ্গার', 220, false, 'fish', 'Crumbed fish fingers with tartare.', 'ব্রেডক্রাম্বের ফিশ ফিঙ্গার, টারটার সসসহ।', [['4 pcs', '৪ পিস', 220, true], ['8 pcs', '৮ পিস', 400, false]], []],
                ['Fish Curry', 'মাছের ঝোল', 280, false, 'fish', 'A light rui curry for rice.', 'ভাতের সাথে হালকা রুইয়ের ঝোল।', [], $riceSide],
                ['Chingri Fry', 'চিংড়ি ফ্রাই', 340, false, 'shrimp', 'Crisp fried prawns, smaller than the malai curry plate.', 'মালাইকারির চেয়ে ছোট, খাস্তা চিংড়ি ফ্রাই।', [], []],
            ]],
            ['Vegetarian', 'নিরামিষ', [
                ['Paneer Butter Masala', 'পনির বাটার মাসালা', 320, true, 'paneer', 'Paneer in the same butter-tomato sauce as the chicken.', 'চিকেনের মতো বাটার-টমেটো সসে পনির।', [], [['Naan', 'নান', 40]]],
                ['Vegetable Sandwich', 'ভেজিটেবল স্যান্ডউইচ', 140, true, 'sandwich', 'Cucumber, tomato, cheese and mint on brown bread.', 'ব্রাউন ব্রেডে শসা, টমেটো, চিজ আর পুদিনা।', [], []],
                ['Club Sandwich', 'ক্লাব স্যান্ডউইচ', 280, false, 'sandwich', 'Chicken, egg, cheese and fries on the side.', 'চিকেন, ডিম, চিজ, সাথে ফ্রাই।', [], []],
                ['Garden Salad', 'গার্ডেন সালাদ', 180, true, 'salad', 'Leaves, cucumber, tomato and a lemon dressing.', 'পাতা, শসা, টমেটো আর লেবুর ড্রেসিং।', [], [['Grilled Chicken', 'গ্রিলড চিকেন', 80]]],
                ['Cream of Mushroom', 'মাশরুম স্যুপ', 160, true, 'tomato-soup', 'A cup of mushroom soup with toast.', 'টোস্টসহ এক কাপ মাশরুম স্যুপ।', [], []],
                ['Alu Bhaji', 'আলু ভাজি', 70, true, 'alu-bhaji', 'Home-style potato fry.', 'ঘরের আলু ভাজি।', [], [['Rice', 'ভাত', 40]]],
            ]],
            ['Breakfast & Bakery', 'নাস্তা ও বেকারি', [
                ['Paratha and Egg', 'পরোটা ও ডিম', 90, false, 'paratha', 'Two flaky paratha and a fried egg.', 'দুইটা খাস্তা পরোটা আর একটা ভাজা ডিম।', [], [['Extra Egg', 'অতিরিক্ত ডিম', 25]]],
                ['Naan', 'নান', 40, true, 'naan', 'Plain tandoor naan. Butter on request.', 'তন্দুরের সাদা নান। মাখন চাইলে বলবেন।', [], [['Butter', 'মাখন', 15]]],
                ['Egg Omelette', 'ডিমের অমলেট', 80, false, 'eggs', 'A two-egg omelette with onion and chilli.', 'দুই ডিমের অমলেট, পেঁয়াজ মরিচসহ।', [], [['Paratha', 'পরোটা', 30]]],
                ['Croissant', 'ক্রোয়েসঁ', 140, true, 'croissant', 'Butter croissant, baked that morning.', 'সকালে বানানো বাটার ক্রোয়েসঁ।', [], []],
                ['Belgian Waffle', 'বেলজিয়ান ওয়াফল', 260, true, 'waffle', 'A warm waffle with ice cream or honey.', 'গরম ওয়াফল, আইসক্রিম বা মধুসহ।', [], [['Ice Cream Scoop', 'আইসক্রিম স্কুপ', 60]]],
            ]],
            ['Desserts', 'মিষ্টান্ন', [
                ['Mishti Doi', 'মিষ্টি দই', 90, true, 'mishti-doi', 'Earthen-pot sweet yogurt.', 'মাটির হাঁড়ির মিষ্টি দই।', [], []],
                ['Firni', 'ফিরনি', 80, true, 'firni', 'Chilled rice firni with pistachio.', 'pista দিয়ে ঠান্ডা চালের ফিরনি।', [], []],
                ['Gulab Jamun', 'গোলাপ জাম', 70, true, 'gulab-jamun', 'Two warm gulab jamun.', 'দুইটা গরম গোলাপ জাম।', [['2 pcs', '২ পিস', 70, true], ['4 pcs', '৪ পিস', 130, false]], []],
                ['Rosogolla', 'রসগোল্লা', 60, true, 'rosogolla', 'Two spongy rosogolla from the counter tin.', 'কাউন্টারের টিন থেকে দুইটা রসগোল্লা।', [], []],
                ['Chocolate Brownie', 'চকলেট ব্রাউনি', 180, true, 'chocolate', 'A dense brownie, warm if the oven is on.', 'ঘন ব্রাউনি, ওভেন চালু থাকলে গরম।', [], [['Ice Cream Scoop', 'আইসক্রিম স্কুপ', 60]]],
                ['New York Cheesecake', 'নিউইয়র্ক চিজকেক', 280, true, 'cheesecake', 'A baked slice with a berry spoon.', 'বেকড স্লাইস, বেরি চামচসহ।', [], []],
                ['Vanilla Ice Cream', 'ভ্যানিলা আইসক্রিম', 120, true, 'icecream', 'Two scoops. Chocolate sauce is optional.', 'দুই স্কুপ। চকলেট সস আলাদা।', [['2 scoops', '২ স্কুপ', 120, true], ['3 scoops', '৩ স্কুপ', 160, false]], []],
                ['Chocolate Lava', 'চকলেট লাভা', 260, true, 'chocolate', 'Warm cake with a liquid centre. Baked to order, so it sells out.', 'গরম কেক, ভেতরটা তরল। অর্ডারে বেক হয়, তাই শেষ হয়ে যায়।', [], [], true],
                ['Donut', 'ডোনাট', 90, true, 'donut', 'Glazed ring donut.', 'গ্লেজ করা ডোনাট।', [], []],
            ]],
            ['Coffee', 'কফি', [
                ['Espresso', 'এসপ্রেসো', 120, true, 'coffee', 'A short shot from the house blend.', 'হাউস ব্লেন্ডের ছোট শট।', [['Single', 'সিঙ্গেল', 120, true], ['Double', 'ডাবল', 160, false]], []],
                ['Americano', 'আমেরিকানো', 140, true, 'coffee', 'Espresso lengthened with hot water.', 'গরম পানি দিয়ে লম্বা করা এসপ্রেসো।', [], []],
                ['Cappuccino', 'ক্যাপুচিনো', 180, true, 'latte', 'Espresso, steamed milk and foam.', 'এসপ্রেসো, স্টিমড দুধ আর ফোম।', [['Regular', 'রেগুলার', 180, true], ['Large', 'লার্জ', 220, false]], []],
                ['Cafe Latte', 'ক্যাফে লাতে', 190, true, 'latte', 'More milk than a cappuccino.', 'ক্যাপুচিনোর চেয়ে বেশি দুধ।', [['Regular', 'রেগুলার', 190, true], ['Large', 'লার্জ', 230, false]], []],
                ['Iced Latte', 'আইসড লাতে', 210, true, 'latte', 'The same latte over ice.', 'একই লাতে, বরফে।', [], []],
                ['Cold Coffee', 'কোল্ড কফি', 180, true, 'milkshake', 'Blended coffee, milk and a little sugar.', 'ব্লেন্ড করা কফি, দুধ আর একটু চিনি।', [], []],
            ]],
            ['Tea & Sharbat', 'চা ও শরবত', [
                ['Masala Tea', 'মসলা চা', 40, true, 'cha', 'Milk tea with cardamom and ginger.', 'এলাচ আর আদা দিয়ে দুধ চা।', [['Cup', 'কাপ', 40, true], ['Mug', 'মগ', 60, false]], []],
                ['Malai Tea', 'মালাই চা', 60, true, 'cha', 'The same tea with a spoon of malai on top.', 'উপরটায় এক চামচ মালাই দিয়ে একই চা।', [], []],
                ['Lacchi Sharbat', 'লাচ্ছি শরবত', 80, true, 'lassi', 'Sweet yogurt lacchi, blended with ice.', 'মিষ্টি দইয়ের লাচ্ছি, বরফসহ ব্লেন্ড।', [['Normal', 'সাধারণ', 80, true], ['Special', 'স্পেশাল', 110, false]], []],
                ['Mango Lassi', 'আমের লাচ্ছি', 130, true, 'mango', 'Mango pulp and yogurt.', 'আমের পাল্প আর দই।', [], []],
                ['Lemon Sharbat', 'লেবু শরবত', 60, true, 'juice', 'Lime, sugar and cold water.', 'লেবু, চিনি আর ঠান্ডা পানি।', [], []],
                ['Mint Lemonade', 'মিন্ট লেমনেড', 90, true, 'mocktail', 'Lemonade muddled with mint.', 'পুদিনা থেঁতো করে লেমনেড।', [], []],
                ['Borhani', 'বোরহানি', 50, true, 'borhani', 'Salty yogurt drink. The right thing with kacchi.', 'নোনতা দইয়ের শরবত। কাচ্চির সাথে মানানসই।', [['Glass', 'গ্লাস', 50, true], ['Jug', 'জগ', 180, false]], []],
                ['Falooda', 'ফালুদা', 160, true, 'falooda', 'Rose milk, vermicelli, basil seed and ice cream.', 'গোলাপ দুধ, সেমাই, তুকমা আর আইসক্রিম।', [], []],
            ]],
            ['Cold Drinks', 'কোল্ড ড্রিংকস', [
                ['Fresh Orange Juice', 'কমলার জুস', 150, true, 'juice', 'Squeezed to order, no bottle.', 'অর্ডারে নিংড়ানো, বোতল নয়।', [], []],
                ['Mango Smoothie', 'আম স্মুদি', 180, true, 'smoothie', 'Mango, yogurt and ice.', 'আম, দই আর বরফ।', [], []],
                ['Virgin Mojito', 'ভার্জিন মোহিটো', 160, true, 'mocktail', 'Lime, mint and soda.', 'লেবু, পুদিনা আর সোডা।', [], []],
                ['Iced Tea', 'আইসড টি', 90, true, 'tea', 'Lemon iced tea.', 'লেবুর আইসড টি।', [], []],
                ['Mineral Water', 'মিনারেল ওয়াটার', 20, true, 'water', 'A half-litre bottle.', 'আধা লিটারের বোতল।', [], []],
                ['Soft Drink', 'কোল্ড ড্রিংকস', 40, true, 'water', 'A canned soft drink from the fridge.', 'ফ্রিজের ক্যান।', [], []],
            ]],
        ];
    }
}
