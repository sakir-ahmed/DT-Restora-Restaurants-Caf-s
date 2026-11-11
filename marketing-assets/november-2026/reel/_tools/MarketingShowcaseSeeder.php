<?php

// Tops up the demo database so every screen used in a reel has real-looking rows (reservations, SMS history,
// letters, stock transfers). Run on the marketing database only:
//   bash _tools/prepare_marketing_db.sh
// It is plain DB inserts, not part of the product. Safe to run once on a fresh `migrate:fresh --seed`.

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return (function () {
    $now = Carbon::now();
    $tables = DB::table('restaurant_tables')->orderBy('id')->get()->groupBy('branch_id');
    $branches = DB::table('branches')->orderBy('id')->get();
    $customers = DB::table('customers')->orderBy('id')->limit(30)->get();

    // ---- reservations: tonight, tomorrow and a few finished ones, across all branches
    $slots = [
        [0, 19, 'booked', 4], [0, 20, 'booked', 6], [0, 21, 'booked', 2], [-1, 20, 'completed', 5], [-1, 19, 'completed', 3],
        [1, 13, 'booked', 8], [1, 20, 'booked', 4], [2, 19, 'booked', 10], [0, 18, 'seated', 4], [-2, 20, 'no_show', 2], [-2, 21, 'cancelled', 4],
    ];
    $n = 0;
    foreach ($slots as $i => [$dayOffset, $hour, $status, $party]) {
        $branch = $branches[$i % $branches->count()];
        $c = $customers[$i % $customers->count()];
        $start = Carbon::now('Asia/Dhaka')->startOfDay()->addDays($dayOffset)->setTime($hour, 0)->utc();  // shown in Dhaka time, stored in UTC
        $deposit = $party >= 6 ? 1000 : 0;
        $id = DB::table('reservations')->insertGetId([
            'branch_id' => $branch->id,
            'booking_no' => 'RSV-'.$start->format('ymd').'-'.str_pad((string) (++$n), 3, '0', STR_PAD_LEFT),
            'guest_name' => $c->name,
            'guest_mobile' => $c->mobile,
            'party_size' => $party,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(90),
            'status' => $status,
            'note' => $party >= 6 ? 'Birthday dinner, please keep a cake table' : ($i % 3 === 0 ? 'Window seat if possible' : null),
            'preorder_total' => 0,
            'deposit_amount' => $deposit,
            'deposit_applied' => 0,
            'deposit_status' => $deposit > 0 ? ($status === 'cancelled' ? 'refunded' : 'held') : 'none',
            'deposit_method' => $deposit > 0 ? 'bkash' : null,
            'deposit_paid_at' => $deposit > 0 ? $start->copy()->subDays(1) : null,
            'created_at' => min($start->copy()->subDays(2), $now->copy()->subHours(2)),
            'updated_at' => min($start->copy()->subDays(1), $now->copy()->subHours(1)),
        ]);
        $branchTables = $tables[$branch->id] ?? collect();
        if ($branchTables->isNotEmpty()) {
            DB::table('reservation_table')->insert(['reservation_id' => $id, 'restaurant_table_id' => $branchTables[$i % $branchTables->count()]->id, 'position' => 0]);
        }
    }

    // ---- SMS history over the last week
    $msgs = [
        ['order_confirmed', 'ডিটি রেস্টোরা: আপনার অর্ডার #%d নিশ্চিত হয়েছে। ধন্যবাদ!'],
        ['invoice', 'DT Restora: আপনার বিল ৳%d। ধন্যবাদ, আবার আসবেন।'],
        ['delivery_status', 'আপনার খাবার রওনা হয়েছে। রাইডার: সোহেল, ২০ মিনিটে পৌঁছাবে।'],
        ['reservation_confirmed', 'আপনার টেবিল বুক হয়েছে আজ রাত ৮টায়। অপেক্ষায় থাকব।'],
        ['due_reminder', 'আপনার বাকি ৳%d। সুবিধামতো পরিশোধ করুন। ধন্যবাদ।'],
        ['birthday', 'শুভ জন্মদিন! আজ আমাদের ওখানে এলে মিষ্টি উপহার।'],
        ['win_back', 'অনেকদিন দেখা নেই! এই সপ্তাহে ১০% ছাড়, কুপন: WELCOME10'],
        ['promotion', 'শুক্রবার বিশেষ অফার: দুটো বার্গারে একটা কোল্ড কফি ফ্রি।'],
    ];
    for ($i = 0; $i < 48; $i++) {
        [$feature, $text] = $msgs[$i % count($msgs)];
        $c = $customers[$i % $customers->count()];
        DB::table('sms_logs')->insert([
            'feature' => $feature, 'mobile' => $c->mobile, 'message' => str_replace('%d', (string) (1000 + $i * 37), $text),
            'provider' => 'bulksms', 'status' => $i % 17 === 5 ? 'failed' : 'sent', 'response' => null,
            'user_id' => 2, 'created_at' => $now->copy()->subHours($i * 3 + 1),
        ]);
    }

    // ---- letters for HR
    foreach ([
        ['Appointment letter', 'Rifat Hasan', 'Appointment as Cashier', 'We are pleased to appoint you as Cashier at DT Restora Gulshan from the first of next month. Your monthly salary is BDT 18,000 with a service charge share. Please report to the branch manager on your first day with your NID copy.'],
        ['Experience certificate', 'Jahangir Alam', 'Experience certificate', 'This is to certify that Jahangir Alam worked as Waiter at DT Restora Gulshan for two years with honesty and good conduct. We wish him success in his next role.'],
        ['Warning letter', 'Kitchen helper', 'Repeated late arrival', 'You have arrived late on five shifts this month. Please follow the shift time. A further delay may affect your salary.'],
        ['Salary increment letter', 'Mizanur Rahman', 'Increment from this month', 'Because of your steady work as Kitchen Manager, your monthly salary increases by BDT 3,000 from this month. Thank you for your effort.'],
    ] as $k => [$title, $to, $subject, $body]) {
        DB::table('letters')->insert([
            'branch_id' => 1, 'author_id' => 2, 'title' => $title, 'recipient' => $to, 'subject' => $subject, 'body' => $body,
            'letter_date' => $now->copy()->subDays($k * 6)->toDateString(), 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    // ---- more stock transfers between branches
    foreach ([[1, 3, 'requested', 'Uttara runs low on chicken for the weekend', [[1, 12], [6, 25]]],
              [2, 1, 'approved', 'Beef for Friday rush', [[2, 10]]],
              [1, 2, 'received', 'Cooking oil and basmati rice', [[6, 30], [4, 6]]]] as $k => [$from, $to, $status, $note, $items]) {
        $id = DB::table('stock_transfers')->insertGetId([
            'from_branch_id' => $from, 'to_branch_id' => $to, 'status' => $status, 'note' => $note,
            'requested_by' => 3, 'approved_by' => $status === 'requested' ? null : 2, 'received_by' => $status === 'received' ? 6 : null,
            'received_at' => $status === 'received' ? $now->copy()->subDays(1) : null,
            'created_at' => $now->copy()->subDays(3 - $k), 'updated_at' => $now,
        ]);
        foreach ($items as [$ing, $qty]) {
            DB::table('stock_transfer_items')->insert(['stock_transfer_id' => $id, 'ingredient_id' => $ing, 'unit_id' => 3, 'qty' => $qty, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    // ---- kitchen: the demo leaves every old order "pending" in the kitchen. Close them, then keep a believable live queue per branch.
    DB::table('orders')->update(['kitchen_status' => 'served']);
    foreach ($branches as $branch) {
        $live = DB::table('orders')->where('branch_id', $branch->id)->where('status', '!=', 'void')
            ->whereIn('order_type', ['dine_in', 'takeaway'])->orderByDesc('id')->limit(11)->pluck('id');
        foreach ($live as $i => $orderId) {
            DB::table('orders')->where('id', $orderId)->update([
                'created_at' => $now->copy()->subMinutes(2 + $i * 2),
                'kitchen_status' => $i < 4 ? 'pending' : ($i < 8 ? 'preparing' : 'ready'),
            ]);
        }
    }

    // ---- dues (baki): a handful of recent sales per branch where the customer still owes part of the bill
    foreach ($branches as $branch) {
        $recent = DB::table('orders')->where('branch_id', $branch->id)->where('status', 'paid')->where('total', '>', 350)
            ->orderByDesc('id')->limit(9)->get();
        foreach ($recent as $i => $order) {
            $customer = $customers[($i + $branch->id * 3) % $customers->count()];
            $paid = round($order->total * [0.0, 0.4, 0.5, 0.6][$i % 4]);
            DB::table('orders')->where('id', $order->id)->update(['is_due' => 1, 'customer_id' => $customer->id, 'paid_at' => $now->copy()->subDays($i + 1)]);
            $pay = DB::table('payments')->where('order_id', $order->id)->orderBy('id')->first();
            if ($pay) {
                if ($paid > 0) {
                    DB::table('payments')->where('id', $pay->id)->update(['amount' => $paid, 'tendered' => $paid, 'change_due' => 0]);
                } else {
                    DB::table('payments')->where('id', $pay->id)->delete();
                }
            }
        }
    }

    // ---- a healthier month: expenses at a believable share of sales
    DB::table('expenses')->update(['amount' => DB::raw('ROUND(amount * 0.55)')]);


    // ---- recipes: the demo only has a dozen. Give every menu item a plausible recipe so cost and profit show everywhere.
    $ing = DB::table('ingredients')->pluck('id', 'name');
    $rules = [
        '/biryani|polao|tehari|khichuri|rice|kacchi/i' => ['Basmati Rice' => 180, 'Cooking Oil' => 25, 'Onion' => 30, 'Yogurt' => 40],
        '/burger/i' => ['Burger Bun' => 1, 'Cheese Slice' => 1, 'Beef' => 120, 'Tomato' => 20],
        '/pizza/i' => ['Pizza Base' => 1, 'Cheese Slice' => 2, 'Tomato' => 40, 'Chicken' => 60],
        '/pasta|alfredo/i' => ['Pasta' => 150, 'Chicken' => 80, 'Cream' => 40, 'Cooking Oil' => 15],
        '/noodle|chowmein|chow mein|mein/i' => ['Noodle' => 150, 'Chicken' => 80, 'Cooking Oil' => 20, 'Onion' => 20],
        '/coffee|latte|americano|cappuccino|mocha|espresso/i' => ['Coffee Beans' => 18, 'Milk' => 150, 'Sugar' => 10],
        '/tea|cha$/i' => ['Tea Leaf' => 5, 'Milk' => 100, 'Sugar' => 12],
        '/lassi|shake|doi|mishti|faluda|rosogolla|gulab|brownie|cheesecake|waffle|ice cream|pudding/i' => ['Milk' => 200, 'Sugar' => 25, 'Cream' => 30],
        '/mojito|lemonade|sharbat|juice/i' => ['Lemon' => 30, 'Mint' => 5, 'Sugar' => 20],
        '/fuchka|chotpoti|samosa|singara|snack|cutlet|fry$/i' => ['Potato' => 80, 'Onion' => 20, 'Tomato' => 20, 'Cooking Oil' => 15],
        '/salad/i' => ['Tomato' => 60, 'Onion' => 30, 'Lemon' => 10],
        '/egg|omelette/i' => ['Egg' => 2, 'Cooking Oil' => 10, 'Onion' => 15],
        '/fish|rui|ilish|prawn|chingri|shrimp/i' => ['Prawn' => 180, 'Onion' => 40, 'Cooking Oil' => 30],
        '/naan|ruti|paratha|bread/i' => ['Flour' => 100, 'Cooking Oil' => 10, 'Butter' => 10],
        '/mutton|rezala|kacchi/i' => ['Mutton' => 220, 'Onion' => 50, 'Cooking Oil' => 30],
        '/beef|bhuna|tehari|kabab|kebab/i' => ['Beef' => 200, 'Onion' => 40, 'Cooking Oil' => 30],
        '/chicken|wings|crispy|chap|tikka|shawarma|sharma/i' => ['Chicken' => 200, 'Onion' => 30, 'Cooking Oil' => 30, 'Flour' => 30],
    ];
    $default = ['Potato' => 100, 'Onion' => 40, 'Cooking Oil' => 20, 'Tomato' => 30];
    $have = DB::table('recipes')->pluck('menu_item_id')->all();
    foreach (DB::table('menu_items')->orderBy('id')->get(['id', 'name']) as $item) {
        if (in_array($item->id, $have, true)) {
            continue;
        }
        $lines = $default;
        foreach ($rules as $pattern => $l) {
            if (preg_match($pattern, $item->name)) {
                $lines = $l;
                break;
            }
        }
        $rid = DB::table('recipes')->insertGetId(['menu_item_id' => $item->id, 'note' => 'House recipe for one regular plate.', 'created_at' => $now, 'updated_at' => $now]);
        foreach ($lines as $name => $qty) {
            if (isset($ing[$name])) {
                DB::table('recipe_items')->insert(['recipe_id' => $rid, 'ingredient_id' => $ing[$name], 'qty' => $qty, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    // beef and mutton are the costly lines; keep their share believable so food cost shows green, not red
    DB::table('recipe_items')->whereIn('ingredient_id', [$ing['Beef'], $ing['Mutton']])->update(['qty' => DB::raw('ROUND(qty * 0.6)')]);


    // ---- activity log: the seed only has journal postings. Add a believable trail of who changed what.
    DB::table('activity_log')->where('description', 'journal.posted')->update(['created_at' => $now->copy()->subDays(2)]);
    DB::table('activity_log')->where('description', '!=', 'journal.posted')->update(['created_at' => $now->copy()->subDay()]);  // seed rows go below the new trail
    $trail = [
        [3, 'order.voided', 'Order', 'Cancelled after the customer left'], [4, 'discount.applied', 'Order', 'Regular customer, 10 percent'],
        [2, 'menu.price_changed', 'MenuItem', 'Kacchi Biryani 540 to 560'], [3, 'purchase.received', 'Purchase', 'Beef 40 kg from Karim Wholesale'],
        [4, 'due.collected', 'Order', 'Collected 670 from Imtiaz Karim'], [2, 'settings.updated', 'Setting', 'VAT and service charge'],
        [3, 'stock.adjusted', 'StockCount', 'Closed the Sunday night count'], [4, 'order.created', 'Order', 'Dine-in table T4'],
        [2, 'recipe.updated', 'Recipe', 'Mutton quantity trimmed'], [3, 'void.approved', 'VoidRequest', 'Approved the void request'],
        [4, 'payment.received', 'Order', 'bKash payment confirmed'], [2, 'staff.updated', 'User', 'New cashier login created'],
        [3, 'wastage.logged', 'Wastage', 'Milk spoiled, 2 litres'], [4, 'order.created', 'Order', 'Takeaway'], [2, 'coupon.created', 'Coupon', 'WELCOME10'],
        [3, 'reservation.created', 'Reservation', 'Table for 6, 8 pm'], [4, 'due.collected', 'Order', 'Collected 342 from Raisa Ahmed'], [2, 'menu.item_added', 'MenuItem', 'New dessert added'],
    ];
    foreach ($trail as $i => [$user, $what, $subject, $note]) {
        DB::table('activity_log')->insert([
            'log_name' => 'default', 'description' => $what, 'subject_type' => 'App\\Models\\'.$subject, 'subject_id' => 1 + $i,
            'event' => null, 'causer_type' => 'App\\Models\\User', 'causer_id' => $user,
            'properties' => json_encode(['note' => $note]), 'created_at' => $now->copy()->subMinutes(4 + $i * 9), 'updated_at' => $now,
        ]);
    }

    // litre-based ingredients (oil, milk, cream) were seeded as litres but recipes use millilitres, which blocks selling at the POS
    DB::table('ingredient_stocks')->whereIn('ingredient_id', DB::table('ingredients')->where('unit_id', 4)->pluck('id'))
        ->update(['stock_qty' => DB::raw('stock_qty * 1000')]);

    echo "showcase data added\n";
})();
