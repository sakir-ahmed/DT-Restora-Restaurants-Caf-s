<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Accounting\PostJournalEntryAction;
use App\Actions\Attendance\RecordDevicePunchAction;
use App\Actions\Payroll\RecordSalaryAdvanceAction;
use App\Actions\Sales\OpenShiftAction;
use App\Models\AggregatorChannel;
use App\Models\AggregatorPayout;
use App\Models\AttendanceDevice;
use App\Models\BlogPost;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\Journal;
use App\Models\LoyaltyEntry;
use App\Models\Media;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderLocation;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StaffAttendance;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VoidRequest;
use App\Models\Voucher;
use App\Services\Accounting\AccountingService;
use Database\Seeders\Support\DemoPhotos;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Everything around the orders: the website, the staff floor,
 * loyalty and a set of books that match the demo sales.
 */
class DemoOperationsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        Setting::current()->update([
            'discount_limit_percent' => '10.00',
            'wastage_spike_multiplier' => '1.50',
            'pos_pin_minutes' => 5,
            'delivery_otp_enabled' => true,
        ]);

        $branches = Branch::query()->whereIn('code', ['BR01', 'BR02', 'BR03'])->get()->keyBy('code');
        $owner = User::query()->where('username', 'owner')->first();
        $accountant = User::query()->where('username', 'accountant')->first();
        $manager = User::query()->where('username', 'manager')->first();

        $this->website($branches, $owner);
        $this->floor($branches, $manager);
        $this->loyalty();
        $this->books($accountant);
    }

    /**
     * @param  Collection<string, Branch>  $branches
     */
    private function website($branches, ?User $author): void
    {
        $gallery = [
            ['restaurant', 'Gulshan dining room', 'গুলশানের ডাইনিং রুম'],
            ['dining', 'A table before service', 'সার্ভিসের আগে একটা টেবিল'],
            ['cafe', 'Coffee counter', 'কফির কাউন্টার'],
            ['kitchen', 'The pass at lunch', 'দুপুরের কিচেন পাস'],
            ['chef', 'Head chef plating kacchi', 'হেড শেফ কাচ্চি সাজাচ্ছেন'],
            ['night', 'Gulshan after dark', 'রাতের গুলশান'],
            ['table', 'Rooftop table', 'ছাদের টেবিল'],
            ['spices', 'The spice tray', 'মসলার ট্রে'],
            ['spread', 'Friday lunch spread', 'শুক্রবারের দুপুরের স্প্রেড'],
            ['coffee-shop', 'Uttara coffee corner', 'উত্তরার কফি কর্নার'],
            ['platter', 'A shared platter', 'শেয়ার করার প্ল্যাটার'],
            ['bbq', 'Tandoor at full heat', 'জোরে জ্বলা তন্দুর'],
        ];

        foreach ($gallery as $order => [$photo, $title, $titleBn]) {
            $path = DemoPhotos::publish($photo);

            if ($path === null) {
                continue;
            }

            Media::query()->updateOrCreate(
                ['title' => $title, 'mediable_type' => null, 'mediable_id' => null],
                [
                    'kind' => Media::KIND_IMAGE,
                    'path' => $path,
                    'title_bn' => $titleBn,
                    'sort_order' => $order + 1,
                    'is_active' => true,
                ],
            );
        }

        foreach (['BR01' => 'restaurant', 'BR02' => 'dining', 'BR03' => 'cafe'] as $code => $photo) {
            $branch = $branches->get($code);
            $path = DemoPhotos::publish($photo);

            if ($branch === null || $path === null) {
                continue;
            }

            Media::query()->updateOrCreate(
                ['mediable_type' => $branch->getMorphClass(), 'mediable_id' => $branch->id, 'kind' => Media::KIND_IMAGE],
                ['path' => $path, 'title' => $branch->name, 'title_bn' => $branch->name_bn, 'sort_order' => 1, 'is_active' => true],
            );
        }

        $posts = [
            ['the-friday-kacchi', 'What goes into a Friday kacchi', 'শুক্রবারের কাচ্চিতে কী থাকে', 'Kitchen', 'biryani', 40],
            ['a-cup-of-malai-tea', 'Why the malai tea has a queue', 'মালাই চায়ে লাইন কেন', 'Cafe', 'tea', 18],
            ['gulshan-after-ten', 'Gulshan after ten', 'রাত দশটার পর গুলশান', 'Outlets', 'night', 12],
            ['how-we-buy-fish', 'How we buy fish in the morning', 'সকালে মাছ কেনা', 'Kitchen', 'fish', 26],
            ['uttara-opens', 'Uttara is open', 'উত্তরা খুলেছে', 'Outlets', 'cafe', 8],
            ['a-note-on-spice', 'Tell us how hot you want it', 'কতটুকু ঝাল চান বলবেন', 'Kitchen', 'spices', 5],
        ];

        foreach ($posts as [$slug, $title, $titleBn, $category, $photo, $daysAgo]) {
            $this->savePost($author, $slug, $title, $titleBn, $category, $photo, BlogPost::STATUS_PUBLISHED, now()->subDays($daysAgo)->setTime(9, 0));
        }

        $this->savePost(
            $author,
            'summer-menu-draft',
            'Summer drinks, still in the notebook',
            'গ্রীষ্মের পানীয়, এখনো খাতায়',
            'Cafe',
            'mango',
            BlogPost::STATUS_DRAFT,
            null,
        );

        $jobs = [
            ['Tandoor cook', 'তন্দুর কুক', 'Kitchen', 'full_time', 'BR01', 20],
            ['Waiter', 'ওয়েটার', 'Service', 'full_time', 'BR02', 30],
            ['Barista', 'বরিস্তা', 'Cafe', 'part_time', 'BR01', 15],
            ['Rider', 'রাইডার', 'Delivery', 'full_time', 'BR03', 25],
            ['Kitchen internship', 'কিচেন ইন্টার্নশিপ', 'Kitchen', 'internship', null, 40],
        ];

        foreach ($jobs as [$title, $titleBn, $department, $type, $branchCode, $closesIn]) {
            $opening = JobOpening::query()->updateOrCreate(
                ['title' => $title, 'department' => $department],
                [
                    'branch_id' => $branchCode !== null ? $branches->get($branchCode)?->id : null,
                    'title_bn' => $titleBn,
                    'employment_type' => $type,
                    'summary' => 'We are hiring a '.$title.' for the DT Restora floor.',
                    'summary_bn' => 'ডিটি রেস্টোরার জন্য '.$titleBn.' নিচ্ছি।',
                    'description' => "You will work a full service, learn the pass, and be named on the roster.\n\nShifts cover lunch and dinner. Friday starts later.",
                    'description_bn' => "আপনাকে পুরো সার্ভিসে থাকতে হবে।\n\nশিফট দুপুর ও রাত। শুক্রবার দেরিতে শুরু।",
                    'requirements' => "A year in a busy kitchen or dining room helps.\nBe on time. Be willing to learn the house recipes.",
                    'requirements_bn' => "ব্যস্ত রান্নাঘর বা ডাইনিংয়ে এক বছর থাকলে ভালো।\nসময়মতো আসুন।",
                    'is_active' => true,
                    'closes_on' => now()->addDays($closesIn)->toDateString(),
                ],
            );

            $this->applications($opening);
        }
    }

    private function applications(JobOpening $opening): void
    {
        $people = [
            ['Rakibul Hasan', '8801811002201', 'new'],
            ['Mitu Rahman', '8801811002202', 'reviewing'],
            ['Sajib Ahmed', '8801811002203', 'shortlisted'],
            ['Lata Khatun', '8801811002204', 'rejected'],
        ];

        if ($opening->title === 'Waiter') {
            $people[] = ['Helal Uddin', '8801811002205', 'hired'];
        }

        foreach ($people as [$name, $mobile, $status]) {
            JobApplication::query()->updateOrCreate(
                ['job_opening_id' => $opening->id, 'mobile' => $mobile],
                [
                    'name' => $name,
                    'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                    'message' => 'I can start this month and I have worked a similar shift before.',
                    'status' => $status,
                ],
            );
        }
    }

    /**
     * @param  Collection<string, Branch>  $branches
     */
    private function floor($branches, ?User $manager): void
    {
        $devices = [
            'BR01' => ['Gulshan front door', 'ZK-GUL-01', 'demo-device-gulshan'],
            'BR02' => ['Dhanmondi staff gate', 'ZK-DHA-01', 'demo-device-dhanmondi'],
            'BR03' => ['Uttara back office', 'ZK-UTT-01', 'demo-device-uttara'],
        ];

        $deviceByBranch = [];

        foreach ($devices as $code => [$name, $serial, $token]) {
            $branch = $branches->get($code);

            if ($branch === null) {
                continue;
            }

            $deviceByBranch[$branch->id] = AttendanceDevice::query()->updateOrCreate(
                ['serial' => $serial],
                [
                    'branch_id' => $branch->id,
                    'name' => $name,
                    'ip_address' => '192.168.10.'.(10 + strlen($code)),
                    'token_hash' => hash('sha256', $token),
                    'is_active' => true,
                    'last_seen_at' => now()->subMinutes(12),
                ],
            );
        }

        $employees = Employee::query()->where('is_active', true)->get();
        $rows = [];
        $punch = app(RecordDevicePunchAction::class);

        foreach ($employees as $employee) {
            for ($day = 21; $day >= 0; $day--) {
                $date = now()->subDays($day)->startOfDay();

                if ($date->dayOfWeekIso === 5 && $employee->id % 3 === 0) {
                    $rows[] = $this->attendanceRow($employee, $date, StaffAttendance::STATUS_ABSENT, null, null);

                    continue;
                }

                $in = $date->copy()->setTime($employee->id % 4 === 0 ? 10 : 9, 5 + ($employee->id % 20));
                $out = $date->copy()->setTime(18, 10 + ($employee->id % 30));

                if ($in->greaterThan(now())) {
                    continue;
                }

                if ($day <= 4 && $employee->device_user_id !== null) {
                    $device = $deviceByBranch[$employee->branch_id] ?? null;

                    if ($device !== null) {
                        $punch->execute($device, $employee->device_user_id, $in, 'in');

                        if ($out->isPast()) {
                            $punch->execute($device, $employee->device_user_id, $out, 'out');
                        }
                    }

                    continue;
                }

                $rows[] = $this->attendanceRow($employee, $date, StaffAttendance::STATUS_PRESENT, $in, $out);
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            StaffAttendance::query()->upsert($chunk, ['employee_id', 'work_date'], ['status', 'clock_in', 'clock_out', 'source', 'branch_id', 'updated_at']);
        }

        $advances = [
            ['8801711100106', 8000, 20, 'Family expense before Eid.'],
            ['8801711100110', 2000, 8, 'Advance against this week.'],
            ['8801711100204', 5000, 15, 'Rent advance.'],
            ['8801711100304', 4000, 6, 'Medical advance.'],
        ];

        foreach ($advances as [$mobile, $amount, $daysAgo, $note]) {
            $employee = Employee::query()->where('mobile', $mobile)->first();

            if ($employee === null) {
                continue;
            }

            $date = now()->subDays($daysAgo)->toDateString();

            if ($employee->salaryAdvances()->whereDate('advanced_on', $date)->exists()) {
                continue;
            }

            $branch = $branches->firstWhere('id', $employee->branch_id);

            if ($branch === null) {
                continue;
            }

            app(RecordSalaryAdvanceAction::class)->execute($branch, $employee, [
                'amount' => $amount,
                'advanced_on' => $date,
                'note' => $note,
            ], $manager);
        }

        foreach ($branches as $branch) {
            $username = match ($branch->code) {
                'BR02' => 'cashier.dhanmondi',
                'BR03' => 'cashier.uttara',
                default => 'cashier',
            };
            $cashier = User::query()->where('username', $username)->first();

            if ($cashier === null) {
                continue;
            }

            for ($day = 14; $day >= 1; $day--) {
                $opened = now()->subDays($day)->setTime(10, 30);

                if (Shift::query()->where('user_id', $cashier->id)->whereDate('opened_at', $opened->toDateString())->exists()) {
                    continue;
                }

                $expected = 5000 + (8000 + ($day * 137));
                $counted = $expected - (($day % 4 === 0) ? 50 : 0);

                Shift::query()->create([
                    'branch_id' => $branch->id,
                    'user_id' => $cashier->id,
                    'terminal_code' => 'T01',
                    'opening_cash' => 5000,
                    'counted_cash' => $counted,
                    'expected_cash' => $expected,
                    'difference' => $counted - $expected,
                    'opened_at' => $opened,
                    'closed_at' => $opened->copy()->setTime(23, 10),
                ]);
            }

            $open = Shift::query()->where('branch_id', $branch->id)->where('user_id', $cashier->id)->whereNull('closed_at')->exists();

            if (! $open) {
                app(OpenShiftAction::class)->execute($branch, $cashier, 5000, 'T01');
            }
        }

        $cashier = User::query()->where('username', 'cashier')->first();

        Order::query()
            ->where('client_id', 'like', 'demo-%')
            ->where('status', 'paid')
            ->latest('id')
            ->limit(3)
            ->get()
            ->each(function (Order $order) use ($cashier, $manager): void {
                $requester = $cashier ?? $manager;

                if ($requester === null) {
                    return;
                }

                VoidRequest::query()->firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'requested_by' => $requester->id,
                        'reason_code' => 'customer_changed_mind',
                        'note' => 'Guest asked to cancel after the ticket was printed.',
                        'status' => 'pending',
                    ],
                );
            });

        Rider::query()->where('is_on_duty', true)->each(function (Rider $rider): void {
            if (RiderLocation::query()->where('rider_id', $rider->id)->where('recorded_at', '>=', now()->subHour())->exists()) {
                return;
            }

            $lat = 23.7925;
            $lng = 90.4078;

            foreach (range(0, 5) as $step) {
                RiderLocation::query()->create([
                    'rider_id' => $rider->id,
                    'latitude' => $lat + ($step * 0.002),
                    'longitude' => $lng + ($step * 0.0015),
                    'accuracy' => 12,
                    'recorded_at' => now()->subMinutes(30 - ($step * 5)),
                ]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceRow(Employee $employee, Carbon $date, string $status, ?Carbon $in, ?Carbon $out): array
    {
        return [
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'work_date' => $date->toDateString(),
            'status' => $status,
            'clock_in' => $in,
            'clock_out' => $out,
            'source' => StaffAttendance::SOURCE_MANUAL,
            'attendance_device_id' => null,
            'recorded_by' => null,
            'created_at' => $date,
            'updated_at' => $date,
        ];
    }

    private function loyalty(): void
    {
        $customers = Customer::query()->where('mobile', 'like', '8801713%')->pluck('id');

        if ($customers->isEmpty()) {
            return;
        }

        LoyaltyEntry::query()->whereIn('customer_id', $customers)->delete();

        $spent = DB::table('orders')
            ->whereIn('customer_id', $customers)
            ->where('status', 'paid')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(total) as spent')
            ->get();

        $rows = [];

        foreach ($spent as $index => $row) {
            $points = (int) floor(((float) $row->spent) / 100);

            if ($points < 1) {
                continue;
            }

            $rows[] = [
                'customer_id' => $row->customer_id,
                'order_id' => null,
                'type' => LoyaltyEntry::TYPE_EARNED,
                'points' => $points,
                'balance_after' => $points,
                'expires_at' => now()->addYear(),
                'note' => 'Points from dining this year',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ];

            if ($index % 4 === 0 && $points > 40) {
                $rows[] = [
                    'customer_id' => $row->customer_id,
                    'order_id' => null,
                    'type' => LoyaltyEntry::TYPE_REDEEMED,
                    'points' => -40,
                    'balance_after' => $points - 40,
                    'expires_at' => null,
                    'note' => 'Redeemed on a dinner bill',
                    'created_at' => now()->subDay(),
                    'updated_at' => now()->subDay(),
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            LoyaltyEntry::query()->insert($chunk);
        }
    }

    private function books(?User $accountant): void
    {
        $previous = now()->subYear();

        FiscalYear::query()->updateOrCreate(
            ['name' => (string) $previous->year],
            [
                'starts_at' => $previous->copy()->startOfYear()->toDateString(),
                'ends_at' => $previous->copy()->endOfYear()->toDateString(),
                'is_locked' => false,
            ],
        );

        $this->expenses($accountant);
        $this->salesJournals($accountant);
        $this->payouts();
        $this->vouchers($accountant);

        $accounting = app(AccountingService::class);

        for ($day = 21; $day >= 1; $day--) {
            $accounting->postDailySalary(now()->subDays($day), $accountant);
        }
    }

    private function expenses(?User $user): void
    {
        $accounting = app(AccountingService::class);
        $branches = Branch::query()->whereIn('code', ['BR01', 'BR02', 'BR03'])->get();
        $rent = ['BR01' => 180000, 'BR02' => 120000, 'BR03' => 95000];

        foreach ($branches as $branch) {
            for ($month = 6; $month >= 0; $month--) {
                $date = now()->subMonths($month)->startOfMonth();

                if ($date->greaterThan(now())) {
                    continue;
                }

                $this->expense($branch, $date, 'rent', 'Shop rent', $rent[$branch->code], $user, $accounting);
                $this->expense($branch, $date->copy()->addDays(4), 'utilities', 'Electricity and gas', 18000 + ($month * 400), $user, $accounting);

                if ($month % 2 === 0) {
                    $this->expense($branch, $date->copy()->addDays(10), 'marketing', 'Facebook and flyer drop', 15000, $user, $accounting);
                }
            }
        }
    }

    private function expense(Branch $branch, Carbon $date, string $category, string $description, float $amount, ?User $user, AccountingService $accounting): void
    {
        $expense = Expense::query()->firstOrCreate(
            ['branch_id' => $branch->id, 'expense_date' => $date->toDateString(), 'description' => $description.' '.$branch->code],
            [
                'category' => $category,
                'amount' => number_format($amount, 2, '.', ''),
                'user_id' => $user?->id,
            ],
        );

        if ($expense->wasRecentlyCreated) {
            $accounting->postExpense($expense, $user);
        }
    }

    private function salesJournals(?User $user): void
    {
        Journal::query()->where('reference', 'like', 'DEMO-SALES-%')->each(function (Journal $journal): void {
            $journal->lines()->delete();
            $journal->delete();
        });

        $month = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', orders.created_at)"
            : "DATE_FORMAT(orders.created_at, '%Y-%m')";

        $sales = DB::table('orders')
            ->where('client_id', 'like', 'demo-%')
            ->where('status', 'paid')
            ->selectRaw($month.' as month')
            ->selectRaw('SUM(subtotal) as subtotal, SUM(discount_amount) as discount, SUM(service_charge) as service_charge, SUM(extra_charge) as extra_charge, SUM(vat_amount) as vat, SUM(delivery_fee) as delivery_fee, SUM(food_cost) as food_cost, SUM(commission_amount) as commission')
            ->groupByRaw($month)
            ->get()
            ->keyBy('month');

        $payments = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.client_id', 'like', 'demo-%')
            ->where('orders.status', 'paid')
            ->where('payments.status', 'completed')
            ->selectRaw($month.' as month, payments.method, SUM(payments.amount) as amount')
            ->groupByRaw($month.', payments.method')
            ->get()
            ->groupBy('month');

        $accounts = [
            'cash' => '1100',
            'bkash' => '1110',
            'nagad' => '1120',
            'card' => '1130',
        ];

        $poster = app(PostJournalEntryAction::class);

        foreach ($sales as $key => $row) {
            $lines = $this->journalLines($row, $payments->get($key, collect()), $accounts);
            $when = Carbon::createFromFormat('Y-m', (string) $key)?->endOfMonth() ?? now();

            if ($when->greaterThan(now())) {
                $when = now();
            }

            $poster->execute(
                entryDate: $when->toDateString(),
                lines: $lines,
                reference: 'DEMO-SALES-'.$key,
                note: 'Demo sales summary for '.$key,
                user: $user,
            );
        }
    }

    /**
     * @param  Collection<int, object>  $payments
     * @param  array<string, string>  $accounts
     * @return list<array{account_code: string, debit: float, credit: float}>
     */
    private function journalLines(object $row, $payments, array $accounts): array
    {
        $lines = [];
        $push = function (string $code, float $debit, float $credit) use (&$lines): void {
            $debit = round($debit, 2);
            $credit = round($credit, 2);

            if ($debit <= 0 && $credit <= 0) {
                return;
            }

            $lines[] = ['account_code' => $code, 'debit' => $debit, 'credit' => $credit];
        };

        $push('4010', (float) $row->discount, 0);
        $push('4000', 0, (float) $row->subtotal + (float) $row->extra_charge + (float) $row->delivery_fee);
        $push('4020', 0, (float) $row->service_charge);
        $push('2100', 0, (float) $row->vat);
        $push('5000', (float) $row->food_cost, 0);
        $push('1300', 0, (float) $row->food_cost);
        $push('5300', (float) $row->commission, 0);
        $push('1200', 0, (float) $row->commission);

        foreach ($payments as $payment) {
            $push($accounts[$payment->method] ?? '1100', (float) $payment->amount, 0);
        }

        $debit = array_sum(array_column($lines, 'debit'));
        $credit = array_sum(array_column($lines, 'credit'));
        $drift = round($debit - $credit, 2);

        if ($drift > 0) {
            $push('4000', 0, $drift);
        } elseif ($drift < 0) {
            $push('4000', abs($drift), 0);
        }

        return $lines;
    }

    private function payouts(): void
    {
        foreach (AggregatorChannel::query()->where('is_active', true)->get() as $channel) {
            for ($month = 3; $month >= 1; $month--) {
                $from = now()->subMonths($month)->startOfMonth();
                $to = $from->copy()->endOfMonth();
                $expected = 40000 + ($channel->id * 5000) + ($month * 2500);

                AggregatorPayout::query()->updateOrCreate(
                    [
                        'aggregator_channel_id' => $channel->id,
                        'period_from' => $from->toDateString(),
                        'period_to' => $to->toDateString(),
                    ],
                    [
                        'expected_amount' => number_format((float) $expected, 2, '.', ''),
                        'received_amount' => $month === 1 ? null : number_format((float) $expected - 350, 2, '.', ''),
                        'status' => $month === 1 ? 'pending' : 'reconciled',
                    ],
                );
            }
        }
    }

    private function vouchers(?User $user): void
    {
        $supplier = Supplier::query()->where('name', 'Karim Wholesale')->first();
        $accounting = app(AccountingService::class);

        if ($supplier === null) {
            return;
        }

        foreach ([1, 2] as $week) {
            $voucher = Voucher::query()->firstOrCreate(
                ['note' => 'Demo: payment to Karim Wholesale, week '.$week],
                [
                    'supplier_id' => $supplier->id,
                    'type' => Voucher::TYPE_PAYMENT,
                    'amount' => number_format(25000 + ($week * 5000), 2, '.', ''),
                    'entry_date' => now()->subWeeks($week)->toDateString(),
                    'pay_from_code' => '1100',
                    'user_id' => $user?->id,
                ],
            );

            if ($voucher->wasRecentlyCreated) {
                try {
                    $accounting->postVoucher($voucher, $user);
                } catch (ValidationException) {
                    $voucher->delete();
                }
            }
        }

        if (DB::table('bank_statement_lines')->where('description', 'like', 'Demo:%')->exists()) {
            return;
        }

        foreach ([1, 2, 3] as $week) {
            DB::table('bank_statement_lines')->insert([
                'account_code' => '1110',
                'occurred_on' => now()->subWeeks($week)->toDateString(),
                'amount' => number_format(18000 + ($week * 1200), 2, '.', ''),
                'description' => 'Demo: bKash settlement week '.$week,
                'user_id' => $user?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function savePost(?User $author, string $slug, string $title, string $titleBn, string $category, string $photo, string $status, ?Carbon $publishedAt): void
    {
        BlogPost::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'author_id' => $author?->id,
                'title' => $title,
                'title_bn' => $titleBn,
                'category' => $category,
                'excerpt' => $title.'. A short note from the DT Restora kitchen and dining rooms.',
                'excerpt_bn' => $titleBn.'। ডিটি রেস্টোরার রান্নাঘর ও ডাইনিং থেকে একটা ছোট লেখা।',
                'body' => $this->postBody($title),
                'body_bn' => $titleBn." নিয়ে আমাদের রান্নাঘরের ছোট করে লেখা।\n\nআমরা প্রতিদিন সকালে বাজার করি, দুপুরে প্লেট সাজাই, আর রাতে কাউন্টার বন্ধ করার আগে যা পড়ে থাকে তা ফেলে দিই। এই লেখাটা সেই দিনের একটা অংশ।",
                'cover_path' => DemoPhotos::publish($photo),
                'cover_alt' => $title,
                'status' => $status,
                'published_at' => $publishedAt,
            ],
        );
    }

    private function postBody(string $title): string
    {
        return $title."\n\nThe kitchen starts before the dining room does. Rice is soaked, the tandoor is lit, and the first tea goes to the people who opened the shutter.\n\nBy noon the pass is full. Kacchi leaves in handis, burgers leave in baskets, and the coffee counter keeps its own queue. Nothing on the ticket is a guess: the recipe card says how much, and the plate goes out when it matches.\n\nIf you are reading this before you visit, order the thing the branch is known for. In Gulshan that is still the kacchi. In Dhanmondi it is the burger and a malai tea. In Uttara, start with the coffee and stay for lunch.\n";
    }
}
