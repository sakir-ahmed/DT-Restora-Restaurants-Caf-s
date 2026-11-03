<?php

declare(strict_types=1);

namespace Database\Seeders\ProductionData\ArmansKitchen;

/**
 * The 36 menu items with their selling price and costed recipe, as supplied by
 * the owner. Quantities are per single serving, in grams, millilitres or pieces.
 * Ingredient prices are per gram, per millilitre or per piece, and are the same
 * everywhere an ingredient appears, so every recipe is costed from one price list.
 *
 * Dishes that the owner built from another dish ("chal base", "Chicken Fry",
 * "Chotpoti base" and so on) are expanded into raw ingredients here, because
 * stock is deducted per ingredient and a recipe holds one line per ingredient.
 */
final class ArmansKitchenMenuCatalog
{
    /**
     * @return list<array{name: string, name_bn: string, sort_order: int}>
     */
    public static function ingredientCategories(): array
    {
        return [
            ['name' => 'Meat & Fish', 'name_bn' => 'মাংস ও মাছ', 'sort_order' => 1],
            ['name' => 'Vegetables', 'name_bn' => 'সবজি', 'sort_order' => 2],
            ['name' => 'Spices & Seasoning', 'name_bn' => 'মসলা', 'sort_order' => 3],
            ['name' => 'Sauces', 'name_bn' => 'সস', 'sort_order' => 4],
            ['name' => 'Dairy & Eggs', 'name_bn' => 'দুগ্ধজাত ও ডিম', 'sort_order' => 5],
            ['name' => 'Grains & Bakery', 'name_bn' => 'চাল, ময়দা ও বেকারি', 'sort_order' => 6],
            ['name' => 'Oil', 'name_bn' => 'তেল', 'sort_order' => 7],
            ['name' => 'Beverage & Sweet', 'name_bn' => 'পানীয় ও মিষ্টি', 'sort_order' => 8],
        ];
    }

    /**
     * Key => name, Bangla name, category, unit (kg = grams, l = millilitres, pcs = pieces)
     * and the price of one gram, millilitre or piece.
     *
     * @return array<string, array{name: string, name_bn: string, category: string, unit: string, price: float}>
     */
    public static function ingredients(): array
    {
        $rows = [
            // Meat & Fish
            'chicken' => ['Chicken', 'মুরগি', 'Meat & Fish', 'kg', 0.185],
            'wings' => ['Chicken Wings', 'উইংস', 'Meat & Fish', 'kg', 0.185],
            'fish' => ['Fish', 'মাছ', 'Meat & Fish', 'kg', 0.45],
            // Vegetables
            'potato' => ['Potato', 'আলু', 'Vegetables', 'kg', 0.025],
            'cucumber' => ['Cucumber', 'শসা', 'Vegetables', 'kg', 0.05],
            'carrot' => ['Carrot', 'গাজর', 'Vegetables', 'kg', 0.05],
            'cabbage' => ['Cabbage', 'বাঁধাকপি', 'Vegetables', 'kg', 0.03],
            'onion' => ['Onion', 'পেঁয়াজ', 'Vegetables', 'kg', 0.05],
            'beans' => ['Yardlong Bean', 'বরবটি', 'Vegetables', 'kg', 0.05],
            'capsicum' => ['Capsicum', 'ক্যাপসিকাম', 'Vegetables', 'kg', 0.1],
            'tomato' => ['Tomato', 'টমেটো', 'Vegetables', 'kg', 0.05],
            'lettuce' => ['Lettuce', 'লেটুস', 'Vegetables', 'kg', 0.1],
            'peas' => ['Dried Peas (Motor)', 'মটর', 'Vegetables', 'kg', 0.08],
            'ginger' => ['Ginger', 'আদা', 'Vegetables', 'kg', 0.15],
            'garlic' => ['Garlic', 'রসুন', 'Vegetables', 'kg', 0.1],
            'lemon' => ['Lemon Juice', 'লেবু', 'Vegetables', 'l', 0.2],
            // Spices & Seasoning
            'salt' => ['Salt', 'লবণ', 'Spices & Seasoning', 'kg', 0.1],
            'black_pepper' => ['Black Pepper', 'গোল মরিচ', 'Spices & Seasoning', 'kg', 0.7],
            'chili_powder' => ['Chili Powder', 'মরিচ গুঁড়া', 'Spices & Seasoning', 'kg', 0.4],
            'cumin' => ['Cumin', 'জিরা', 'Spices & Seasoning', 'kg', 0.6],
            'chat_masala' => ['Chat Masala', 'চাট মসলা', 'Spices & Seasoning', 'kg', 0.5],
            'tamarind' => ['Tamarind', 'তেঁতুল', 'Spices & Seasoning', 'kg', 0.15],
            'cardamom' => ['Cardamom', 'এলাচ', 'Spices & Seasoning', 'kg', 1.0],
            'black_salt' => ['Beet Salt', 'বিট লবণ', 'Spices & Seasoning', 'kg', 0.2],
            // Sauces
            'soy_sauce' => ['Soy Sauce', 'সয়া সস', 'Sauces', 'l', 0.2],
            'fish_sauce' => ['Fish Sauce', 'ফিশ সস', 'Sauces', 'l', 0.2],
            'mayonnaise' => ['Mayonnaise', 'মেয়োনিজ', 'Sauces', 'kg', 0.4],
            'chili_sauce' => ['Chili Sauce', 'চিলি সস', 'Sauces', 'kg', 0.1],
            'tomato_sauce' => ['Tomato Sauce', 'টমেটো সস', 'Sauces', 'kg', 0.05],
            'bbq_sauce' => ['BBQ Sauce', 'বিবিকিউ সস', 'Sauces', 'kg', 0.2],
            'thum_sauce' => ['Thum Sauce', 'থুম সস', 'Sauces', 'kg', 0.4],
            'naga_sauce' => ['Naga Sauce', 'নাগা সস', 'Sauces', 'kg', 0.2],
            'burger_sauce' => ['Burger Sauce', 'সস', 'Sauces', 'kg', 0.2],
            // Dairy & Eggs
            'egg' => ['Egg', 'ডিম', 'Dairy & Eggs', 'pcs', 12.0],
            'milk' => ['Milk', 'দুধ', 'Dairy & Eggs', 'l', 0.075],
            'yogurt' => ['Yogurt', 'দই', 'Dairy & Eggs', 'kg', 0.15],
            'cheese' => ['Cheese Slice', 'চিজ', 'Dairy & Eggs', 'pcs', 12.0],
            'ghee' => ['Ghee', 'ঘি', 'Dairy & Eggs', 'kg', 0.7],
            // Grains & Bakery
            'rice' => ['Rice', 'চাল', 'Grains & Bakery', 'kg', 0.09],
            'noodles' => ['Noodles', 'নুডলস', 'Grains & Bakery', 'kg', 0.12],
            'flour' => ['Flour (Maida)', 'ময়দা', 'Grains & Bakery', 'kg', 0.075],
            'cornflour' => ['Cornflour', 'কর্নফ্লাওয়ার', 'Grains & Bakery', 'kg', 0.12],
            'bread_crumb' => ['Bread Crumb', 'ব্রেড ক্রাম্ব', 'Grains & Bakery', 'kg', 0.15],
            'semolina' => ['Semolina (Suji)', 'সুজি', 'Grains & Bakery', 'kg', 0.07],
            'bun' => ['Burger Bun', 'বান', 'Grains & Bakery', 'pcs', 15.0],
            'student_bun' => ['Student Burger Bun', 'স্টুডেন্ট বান', 'Grains & Bakery', 'pcs', 10.0],
            // Oil
            'oil' => ['Cooking Oil', 'তেল', 'Oil', 'l', 0.195],
            // Beverage & Sweet
            'tea_leaf' => ['Tea Leaf', 'চা পাতা', 'Beverage & Sweet', 'kg', 0.3333],
            'sugar' => ['Sugar', 'চিনি', 'Beverage & Sweet', 'kg', 0.12],
            'coffee' => ['Coffee', 'কফি', 'Beverage & Sweet', 'kg', 1.0],
            'horlicks' => ['Horlicks', 'হরলিক্স', 'Beverage & Sweet', 'kg', 0.4],
            'honey' => ['Honey', 'মধু', 'Beverage & Sweet', 'kg', 0.3],
            'cashew' => ['Cashew Nut', 'কাজু', 'Beverage & Sweet', 'kg', 1.2],
            'choco_syrup' => ['Chocolate Syrup', 'চকলেট সিরাপ', 'Beverage & Sweet', 'kg', 0.3],
            'ice_cream' => ['Ice Cream (Scoop)', 'আইসক্রিম', 'Beverage & Sweet', 'pcs', 10.0],
        ];

        $ingredients = [];

        foreach ($rows as $key => [$name, $nameBn, $category, $unit, $price]) {
            $ingredients[$key] = [
                'name' => $name,
                'name_bn' => $nameBn,
                'category' => $category,
                'unit' => $unit,
                'price' => $price,
            ];
        }

        return $ingredients;
    }

    /**
     * Menu categories in display order, each with its items.
     *
     * @return list<array{name: string, name_bn: string, items: list<array{0: string, 1: string, 2: int, 3: array<string, float|int>}>}>
     *                                                                                                                                   Each item is [name, Bangla name, price, recipe by ingredient key].
     */
    public static function menu(): array
    {
        $chickenFry = [
            'chicken' => 250, 'ginger' => 10, 'garlic' => 10, 'chili_powder' => 5, 'black_pepper' => 3,
            'salt' => 5, 'soy_sauce' => 5, 'flour' => 40, 'cornflour' => 20, 'egg' => 0.5,
            'bread_crumb' => 40, 'oil' => 40,
        ];

        $riceBase = [
            'rice' => 180, 'egg' => 1, 'carrot' => 30, 'beans' => 30, 'onion' => 20,
            'soy_sauce' => 10, 'oil' => 30,
        ];

        $chotpotiBase = [
            'peas' => 150, 'potato' => 50, 'tamarind' => 15, 'chili_powder' => 2, 'cumin' => 2,
            'chat_masala' => 2, 'salt' => 5,
        ];

        $fuchkaBase = [
            'semolina' => 50, 'flour' => 20, 'peas' => 40, 'potato' => 30, 'tamarind' => 20, 'oil' => 40,
        ];

        $burgerBase = [
            'bun' => 1, 'chicken' => 60, 'flour' => 15, 'cornflour' => 10, 'mayonnaise' => 15, 'oil' => 20,
        ];

        $jumboBase = [
            'bun' => 1, 'chicken' => 150, 'flour' => 30, 'egg' => 0.5, 'oil' => 30,
        ];

        return [
            [
                'name' => 'Starters', 'name_bn' => 'স্টার্টার',
                'items' => [
                    ['French Fry', 'ফ্রেঞ্চ ফ্রাই', 100, [
                        'potato' => 200, 'cornflour' => 15, 'salt' => 5, 'black_pepper' => 1, 'oil' => 25,
                    ]],
                    ['Chicken Fry (1 Pc)', 'চিকেন ফ্রাই (১ পিস)', 120, $chickenFry],
                    ['Chicken Meatbox', 'চিকেন মিটবক্স', 200, [
                        'potato' => 100, 'chicken' => 120, 'ginger' => 5, 'garlic' => 5, 'chili_powder' => 2,
                        'salt' => 3, 'soy_sauce' => 5, 'flour' => 20, 'cornflour' => 10, 'bread_crumb' => 20,
                        'mayonnaise' => 30, 'chili_sauce' => 10, 'tomato_sauce' => 20, 'capsicum' => 20, 'oil' => 35,
                    ]],
                    ['Crispy Wings', 'ক্রিস্পি উইংস', 260, [
                        'wings' => 200, 'ginger' => 10, 'garlic' => 10, 'chili_powder' => 3, 'black_pepper' => 2,
                        'salt' => 5, 'soy_sauce' => 5, 'flour' => 40, 'cornflour' => 20, 'oil' => 30,
                    ]],
                    ['BBQ Wings (6 Pcs)', 'বিবিকিউ উইংস (৬ পিস)', 280, [
                        'wings' => 400, 'ginger' => 20, 'garlic' => 20, 'chili_powder' => 6, 'black_pepper' => 4,
                        'salt' => 10, 'soy_sauce' => 10, 'flour' => 80, 'cornflour' => 40, 'oil' => 60,
                        'bbq_sauce' => 60, 'honey' => 20,
                    ]],
                    ['Cashewnut Salad', 'কাজুবাদাম সালাদ', 280, [
                        'chicken' => 70, 'cashew' => 15, 'cucumber' => 30, 'carrot' => 20, 'cabbage' => 30,
                        'onion' => 20, 'mayonnaise' => 25, 'lemon' => 5, 'salt' => 2, 'black_pepper' => 1, 'oil' => 10,
                    ]],
                ],
            ],
            [
                'name' => 'Meals', 'name_bn' => 'মিল',
                'items' => [
                    ['Chicken Strip with Rice', 'চিকেন স্ট্রিপ উইথ রাইস', 300, [
                        'chicken' => 150, 'ginger' => 5, 'garlic' => 5, 'flour' => 30, 'cornflour' => 15,
                        'egg' => 0.5, 'bread_crumb' => 30, 'rice' => 150, 'oil' => 45,
                    ]],
                    ['Chicken Strip with Fries', 'চিকেন স্ট্রিপ উইথ ফ্রাইস', 300, [
                        'chicken' => 150, 'flour' => 30, 'cornflour' => 15, 'bread_crumb' => 30, 'egg' => 0.5,
                        'potato' => 100, 'oil' => 50,
                    ]],
                    ['Fish Fry Thai', 'ফিশ ফ্রাই থাই', 300, [
                        'fish' => 200, 'ginger' => 10, 'garlic' => 10, 'fish_sauce' => 10, 'soy_sauce' => 5,
                        'lemon' => 10, 'salt' => 3, 'flour' => 30, 'cornflour' => 15, 'oil' => 35,
                    ]],
                    ['Fried Rice with Chicken Fry', 'ফ্রাইড রাইস উইথ চিকেন ফ্রাই', 180, self::merge($riceBase, [
                        'black_pepper' => 1, 'salt' => 3,
                    ], $chickenFry)],
                    ['Fried Rice with Chili Chicken', 'ফ্রাইড রাইস উইথ চিলি চিকেন', 200, self::merge($riceBase, [
                        'chicken' => 120, 'soy_sauce' => 10, 'chili_sauce' => 10, 'cornflour' => 15,
                        'capsicum' => 20, 'oil' => 30,
                    ])],
                    ['Fried Rice with BBQ Wings (2 Pcs)', 'ফ্রাইড রাইস উইথ বিবিকিউ উইংস (২ পিস)', 220, self::merge($riceBase, [
                        'wings' => 130, 'flour' => 25, 'cornflour' => 15, 'oil' => 20, 'bbq_sauce' => 20, 'honey' => 10,
                    ])],
                    ['Fried Rice with BBQ Chicken', 'ফ্রাইড রাইস উইথ বিবিকিউ চিকেন', 220, self::merge($riceBase, [
                        'chicken' => 150, 'bbq_sauce' => 30, 'oil' => 20,
                    ])],
                    ['Chicken Chowmein', 'চিকেন চাউমিন', 230, [
                        'noodles' => 150, 'chicken' => 100, 'egg' => 1, 'carrot' => 30, 'cabbage' => 40,
                        'capsicum' => 20, 'onion' => 30, 'soy_sauce' => 15, 'oil' => 30,
                    ]],
                ],
            ],
            [
                'name' => 'Sandwich & Shawarma', 'name_bn' => 'স্যান্ডউইচ ও শর্মা',
                'items' => [
                    ['Chicken Sub Sandwich', 'চিকেন সাব স্যান্ডউইচ', 150, [
                        'bun' => 1, 'chicken' => 80, 'mayonnaise' => 20, 'cheese' => 1, 'tomato' => 20,
                        'lettuce' => 20, 'oil' => 15,
                    ]],
                    ['Chicken Sharma', 'চিকেন শর্মা', 150, [
                        'flour' => 80, 'oil' => 30, 'chicken' => 100, 'thum_sauce' => 20, 'cucumber' => 20,
                    ]],
                ],
            ],
            [
                'name' => 'Chotpoti & Fuchka', 'name_bn' => 'চটপটি ও ফুচকা',
                'items' => [
                    ['Regular Chotpoti', 'রেগুলার চটপটি', 70, $chotpotiBase],
                    ['Shahi Chotpoti', 'শাহী চটপটি', 100, self::merge($chotpotiBase, [
                        'chicken' => 50, 'egg' => 0.5, 'ghee' => 10,
                    ])],
                    ['Regular Fuchka', 'রেগুলার ফুচকা', 80, $fuchkaBase],
                    ['Churmur', 'চুরমুর', 100, $fuchkaBase],
                    ['Doi Fuchka', 'দই ফুচকা', 140, self::merge($fuchkaBase, ['yogurt' => 50, 'sugar' => 5])],
                ],
            ],
            [
                'name' => 'Burgers', 'name_bn' => 'বার্গার',
                'items' => [
                    ['Student Burger', 'স্টুডেন্ট বার্গার', 70, [
                        'student_bun' => 1, 'chicken' => 40, 'flour' => 10, 'egg' => 0.25, 'oil' => 15,
                        'burger_sauce' => 10,
                    ]],
                    ['Regular Burger', 'রেগুলার বার্গার', 120, $burgerBase],
                    ['Naga Burger', 'নাগা বার্গার', 130, self::merge($burgerBase, ['naga_sauce' => 10])],
                    ['Cheesy Burger', 'চিজি বার্গার', 170, self::merge($burgerBase, ['cheese' => 1])],
                    ['Jumbo Burger', 'জাম্বো বার্গার', 250, $jumboBase],
                    ['Cheesy Jumbo Burger', 'চিজি জাম্বো বার্গার', 300, self::merge($jumboBase, ['cheese' => 2])],
                ],
            ],
            [
                'name' => 'Tea, Coffee & Drinks', 'name_bn' => 'চা, কফি ও পানীয়',
                'items' => [
                    ['Rong Cha', 'রং চা', 10, ['tea_leaf' => 3, 'sugar' => 5]],
                    ['Masala Rong Cha', 'মসলা রং চা', 20, [
                        'tea_leaf' => 3, 'sugar' => 5, 'ginger' => 5, 'cardamom' => 1,
                    ]],
                    ['Dudh Cha', 'দুধ চা', 30, ['milk' => 40, 'tea_leaf' => 3, 'sugar' => 10]],
                    ['Pora Ruti', 'পোড়া রুটি', 30, ['flour' => 80, 'oil' => 10]],
                    ['Regular Coffee', 'রেগুলার কফি', 40, ['coffee' => 5, 'milk' => 100, 'sugar' => 10]],
                    ['Horlicks Dudh Cha', 'হরলিক্স দুধ চা', 50, [
                        'milk' => 100, 'tea_leaf' => 3, 'sugar' => 10, 'horlicks' => 15,
                    ]],
                    ['Lassi', 'লাচ্ছি', 100, ['yogurt' => 120, 'sugar' => 20, 'black_salt' => 2]],
                    ['Cold Coffee', 'কোল্ড কফি', 100, ['milk' => 150, 'coffee' => 5, 'sugar' => 15]],
                    ['Chocolate Milk Shake', 'চকলেট মিল্ক শেক', 100, [
                        'milk' => 200, 'choco_syrup' => 20, 'sugar' => 10, 'ice_cream' => 1,
                    ]],
                ],
            ],
        ];
    }

    /**
     * Add recipe lines together, summing the quantity of an ingredient that appears more than once.
     *
     * @param  array<string, float|int>  ...$parts
     * @return array<string, float|int>
     */
    private static function merge(array ...$parts): array
    {
        $merged = [];

        foreach ($parts as $part) {
            foreach ($part as $ingredient => $qty) {
                $merged[$ingredient] = ($merged[$ingredient] ?? 0) + $qty;
            }
        }

        return $merged;
    }
}
