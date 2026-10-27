<?php

declare(strict_types=1);

use App\Actions\Sales\CreateOrderAction;
use App\Enums\OrderType;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Shift;
use App\Models\User;
use App\Services\InvoiceNumberService;
use App\Services\PosSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class, UnitSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->owner = User::factory()->role('owner')->create();
    $this->cashier = User::factory()->role('cashier', $this->branch->id)->create();

    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => MenuCategory::factory()->create(['branch_id' => $this->branch->id])->id,
        'base_price' => '100.00',
    ]);

    $this->shift = Shift::query()->create([
        'branch_id' => $this->branch->id,
        'user_id' => $this->cashier->id,
        'terminal_code' => 'T01',
        'opening_cash' => 0,
        'opened_at' => now(),
    ]);
});

function branchSettingsPayload(array $overrides = []): array
{
    $pos = array_replace_recursive(PosSettings::defaults(), $overrides['pos'] ?? []);

    if (isset($overrides['pos']['cash_suggestions'])) {
        $pos['cash_suggestions'] = $overrides['pos']['cash_suggestions'];
    }

    return [
        'vat_percent' => $overrides['vat_percent'] ?? '7.50',
        'vat_inclusive' => $overrides['vat_inclusive'] ?? true,
        'service_charge_percent' => $overrides['service_charge_percent'] ?? '5.00',
        'receipt_footer' => $overrides['receipt_footer'] ?? 'Thanks',
        'pos' => $pos,
    ];
}

it('falls back to the default button matrix and invoice pattern', function (): void {
    $settings = PosSettings::for($this->branch);

    expect($settings['actions']['send_to_kitchen']['enabled'])->toBeTrue()
        ->and($settings['actions']['quick_save']['enabled'])->toBeFalse()
        ->and($settings['actions']['quick_save']['exact_amount'])->toBeTrue()
        ->and($settings['invoice_pattern'])->toBe(PosSettings::DEFAULT_PATTERN)
        ->and($settings['invoice_layout'])->toBe('classic');
});

it('saves branch POS settings with VAT and sends them to the POS page', function (): void {
    $payload = branchSettingsPayload(['pos' => [
        'actions' => ['quick_save' => ['enabled' => true, 'print_invoice' => true, 'print_kitchen' => true]],
        'invoice_layout' => 'bold',
    ]]);

    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.settings.branch', $this->branch), $payload)
        ->assertRedirect();

    $branch = $this->branch->fresh();

    expect($branch->vat_percent)->toBe('7.50')
        ->and($branch->vat_inclusive)->toBeTrue()
        ->and(PosSettings::for($branch)['actions']['quick_save']['print_kitchen'])->toBeTrue()
        ->and(PosSettings::for($branch)['invoice_layout'])->toBe('bold');

    $this->actingAs($this->cashier, 'staff')->get('/admin/pos')->assertInertia(fn ($page) => $page
        ->where('branch.pos_settings.actions.quick_save.enabled', true)
        ->where('branch.pos_settings.invoice_layout', 'bold'));
});

it('keeps settings separate for each branch', function (): void {
    $other = Branch::factory()->create(['code' => 'BR02']);

    $this->actingAs($this->owner, 'staff')->put(route('admin.settings.branch', $this->branch), branchSettingsPayload([
        'pos' => ['actions' => ['quick_save' => ['enabled' => true]]],
    ]))->assertRedirect();

    expect(PosSettings::for($this->branch->fresh())['actions']['quick_save']['enabled'])->toBeTrue()
        ->and(PosSettings::for($other->fresh())['actions']['quick_save']['enabled'])->toBeFalse();
});

it('rejects invoice patterns that could repeat invoice numbers', function (string $pattern): void {
    $this->actingAs($this->owner, 'staff')
        ->put(route('admin.settings.branch', $this->branch), branchSettingsPayload(['pos' => ['invoice_pattern' => $pattern]]))
        ->assertSessionHasErrors('pos.invoice_pattern');
})->with([
    'no sequence' => '{BRANCH}-{TERMINAL}-{YYYY}{MM}{DD}',
    'no date' => '{BRANCH}-{TERMINAL}-{SEQ}',
    'no terminal' => '{BRANCH}-{YYYY}{MM}{DD}-{SEQ}',
    'unknown token' => '{BRANCH}-{TERMINAL}-{YYYY}{MM}{DD}-{SEQ}-{FOO}',
    'unsafe characters' => '{BRANCH} {TERMINAL} {YYYY}{MM}{DD} {SEQ}',
]);

it('numbers invoices with the branch pattern and claims device numbers back', function (): void {
    $this->branch->update(['pos_settings' => [
        'invoice_pattern' => 'INV/{BRANCH}/{TERMINAL}/{YY}{MM}{DD}/{SEQ}',
        'invoice_seq_digits' => 5,
    ]]);

    $service = app(InvoiceNumberService::class);
    $day = now()->format('ymd');

    expect($service->next($this->branch->fresh(), 'T01'))->toBe("INV/BR01/T01/{$day}/00001")
        ->and($service->claim($this->branch->fresh(), 'T01', "INV/BR01/T01/{$day}/00007"))->toBe("INV/BR01/T01/{$day}/00007")
        ->and($service->next($this->branch->fresh(), 'T01'))->toBe("INV/BR01/T01/{$day}/00008");
});

it('prints the receipt in the branch layout and a priceless kitchen token', function (): void {
    $this->branch->update(['pos_settings' => ['invoice_layout' => 'compact']]);

    $order = app(CreateOrderAction::class)->execute(
        branch: $this->branch->fresh(),
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 3]],
        cashier: $this->cashier,
        shift: $this->shift,
        terminalCode: 'T01',
    );

    $this->actingAs($this->cashier, 'staff')->get(route('admin.orders.receipt', $order))
        ->assertOk()
        ->assertSee('layout-compact', false);

    $this->actingAs($this->cashier, 'staff')->get(route('admin.orders.kitchen-token', $order))
        ->assertOk()
        ->assertSee($order->invoice_no)
        ->assertSee('3 x '.$this->item->name)
        ->assertDontSee('300.00');
});

it('lets only users with the settings permission change branch settings', function (): void {
    $this->actingAs($this->cashier, 'staff')
        ->put(route('admin.settings.branch', $this->branch), branchSettingsPayload())
        ->assertForbidden();
});

it('keeps payment methods in the chosen order and hides the ones turned off', function (): void {
    $this->actingAs($this->owner, 'staff')->put(route('admin.settings.branch', $this->branch), branchSettingsPayload([
        'pos' => ['payment_methods' => [
            ['method' => 'bkash', 'enabled' => true],
            ['method' => 'cash', 'enabled' => true],
            ['method' => 'card', 'enabled' => false],
            ['method' => 'nagad', 'enabled' => false],
        ]],
    ]))->assertRedirect();

    $branch = $this->branch->fresh();

    expect(PosSettings::enabledMethods($branch))->toBe(['bkash', 'cash'])
        ->and(array_column(PosSettings::for($branch)['payment_methods'], 'method'))->toBe(['bkash', 'cash', 'card', 'nagad']);
});

it('adds payment methods a branch has not seen yet at the end', function (): void {
    $this->branch->update(['pos_settings' => ['payment_methods' => [['method' => 'card', 'enabled' => true]]]]);

    expect(array_column(PosSettings::for($this->branch->fresh())['payment_methods'], 'method'))->toBe(['card', 'cash', 'bkash', 'nagad']);
});

it('refuses to turn every payment method off', function (): void {
    $this->actingAs($this->owner, 'staff')->put(route('admin.settings.branch', $this->branch), branchSettingsPayload([
        'pos' => ['payment_methods' => array_map(fn (string $method): array => ['method' => $method, 'enabled' => false], ['cash', 'bkash', 'nagad', 'card'])],
    ]))->assertSessionHasErrors('pos.payment_methods');
});

it('sends the enabled payment methods to the POS page', function (): void {
    $this->branch->update(['pos_settings' => ['payment_methods' => [
        ['method' => 'card', 'enabled' => true],
        ['method' => 'cash', 'enabled' => true],
        ['method' => 'bkash', 'enabled' => false],
        ['method' => 'nagad', 'enabled' => false],
    ]]]);

    $this->actingAs($this->cashier, 'staff')->get('/admin/pos')->assertInertia(fn ($page) => $page
        ->where('branch.pos_settings.payment_methods.0.method', 'card')
        ->where('branch.pos_settings.payment_methods.2.enabled', false));
});

it('pays an order by card and enforces the branch payment methods', function (): void {
    $order = app(CreateOrderAction::class)->execute(
        branch: $this->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $this->item->id, 'quantity' => 1]],
        cashier: $this->cashier,
        shift: $this->shift,
        terminalCode: 'T01',
    );

    $this->branch->update(['pos_settings' => ['payment_methods' => [
        ['method' => 'cash', 'enabled' => false],
        ['method' => 'card', 'enabled' => true],
        ['method' => 'bkash', 'enabled' => false],
        ['method' => 'nagad', 'enabled' => false],
    ]]]);

    $this->actingAs($this->cashier, 'staff')->post(route('admin.orders.pay', $order), ['tendered' => 500])
        ->assertSessionHasErrors('tendered');

    $this->actingAs($this->cashier, 'staff')->post(route('admin.payments.split', $order), ['lines' => [
        ['method' => 'cash', 'amount' => 100],
        ['method' => 'card', 'amount' => (float) $order->total - 100],
    ]])->assertSessionHasErrors('lines.0.method');

    $this->actingAs($this->cashier, 'staff')->post(route('admin.payments.single', $order), ['method' => 'card', 'reference' => 'SLIP-9'])
        ->assertRedirect();

    $order->refresh();

    expect($order->status->value)->toBe('paid')
        ->and($order->payments()->first()->method)->toBe('card')
        ->and($order->payments()->first()->reference)->toBe('SLIP-9')
        ->and((float) $order->payments()->first()->amount)->toBe((float) $order->total);
});

function posOrder($test, int $quantity = 1)
{
    return app(CreateOrderAction::class)->execute(
        branch: $test->branch,
        type: OrderType::Takeaway,
        lines: [['menu_item_id' => $test->item->id, 'quantity' => $quantity]],
        cashier: $test->cashier,
        shift: $test->shift,
        terminalCode: 'T01',
    );
}

it('seeds a full set of payment methods including bank POS terminals', function (): void {
    $this->seed(PaymentMethodSeeder::class);

    expect(PaymentMethod::query()->pluck('type', 'code')->all())->toMatchArray([
        'cash' => 'cash',
        'bkash' => 'wallet',
        'rocket' => 'wallet',
        'ebl_pos' => 'card',
        'city_pos' => 'card',
        'mtb_pos' => 'card',
    ]);

    // Running it again does not duplicate anything.
    $this->seed(PaymentMethodSeeder::class);

    expect(PaymentMethod::query()->where('code', 'ebl_pos')->count())->toBe(1);
});

it('lets an admin add, edit and delete payment methods', function (): void {
    $this->actingAs($this->owner, 'staff')->post(route('admin.settings.payment-methods.store'), [
        'code' => 'ebl_pos', 'name' => 'EBL POS', 'name_bn' => null, 'type' => 'card', 'is_active' => true,
    ])->assertRedirect();

    $method = PaymentMethod::query()->where('code', 'ebl_pos')->firstOrFail();

    expect($method->is_system)->toBeFalse();

    $this->actingAs($this->owner, 'staff')->put(route('admin.settings.payment-methods.update', $method), [
        'name' => 'EBL Bank POS', 'name_bn' => null, 'type' => 'other', 'is_active' => true,
    ])->assertRedirect();

    expect($method->fresh()->name)->toBe('EBL Bank POS')->and($method->fresh()->type)->toBe('other');

    $this->actingAs($this->owner, 'staff')->delete(route('admin.settings.payment-methods.destroy', $method))->assertRedirect();

    expect(PaymentMethod::query()->where('code', 'ebl_pos')->exists())->toBeFalse();
});

it('validates payment method codes and protects built-in and used methods', function (): void {
    $store = fn (array $data) => $this->actingAs($this->owner, 'staff')->post(route('admin.settings.payment-methods.store'), $data + [
        'name' => 'X', 'type' => 'wallet', 'is_active' => true,
    ]);

    $store(['code' => 'Bad Code'])->assertSessionHasErrors('code');
    $store(['code' => 'bkash'])->assertSessionHasErrors('code');
    $store(['code' => 'drawer', 'type' => 'cash'])->assertSessionHasErrors('type');

    $cash = PaymentMethod::findByCode('cash');

    $this->actingAs($this->owner, 'staff')->delete(route('admin.settings.payment-methods.destroy', $cash))->assertSessionHas('error');
    $this->actingAs($this->owner, 'staff')->put(route('admin.settings.payment-methods.update', $cash), [
        'name' => 'Cash', 'type' => 'card', 'is_active' => false,
    ])->assertSessionHasErrors('type');

    $used = PaymentMethod::query()->create(['code' => 'rocket', 'name' => 'Rocket', 'type' => 'wallet', 'sort_order' => 9]);
    $order = posOrder($this);
    Payment::query()->create(['order_id' => $order->id, 'method' => 'rocket', 'amount' => $order->total, 'status' => 'completed']);

    $this->actingAs($this->owner, 'staff')->delete(route('admin.settings.payment-methods.destroy', $used))->assertSessionHas('error');
    expect($used->fresh())->not->toBeNull();

    $this->actingAs($this->cashier, 'staff')->post(route('admin.settings.payment-methods.store'), ['code' => 'upay', 'name' => 'Upay', 'type' => 'wallet', 'is_active' => true])->assertForbidden();
});

it('offers a newly added method to every branch and drops it when switched off', function (): void {
    PaymentMethod::query()->create(['code' => 'city_pos', 'name' => 'City POS', 'type' => 'card', 'sort_order' => 9]);

    $list = PosSettings::for($this->branch)['payment_methods'];

    expect(end($list))->toMatchArray(['method' => 'city_pos', 'enabled' => true, 'name' => 'City POS', 'type' => 'card']);

    PaymentMethod::findByCode('city_pos')->update(['is_active' => false]);

    expect(PosSettings::enabledMethods($this->branch))->not->toContain('city_pos');
});

it('completes a bank POS payment at once and keeps a wallet payment pending', function (): void {
    PaymentMethod::query()->create(['code' => 'ebl_pos', 'name' => 'EBL POS', 'type' => 'card', 'sort_order' => 8]);
    PaymentMethod::query()->create(['code' => 'rocket', 'name' => 'Rocket', 'type' => 'wallet', 'sort_order' => 9]);

    $single = posOrder($this);

    $this->actingAs($this->cashier, 'staff')->post(route('admin.payments.single', $single), ['method' => 'ebl_pos', 'reference' => 'SLIP-1'])->assertRedirect();

    expect($single->fresh()->status->value)->toBe('paid')
        ->and($single->payments()->first()->method)->toBe('ebl_pos');

    $split = posOrder($this, 2);
    $half = (float) $split->total / 2;

    $this->actingAs($this->cashier, 'staff')->post(route('admin.payments.split', $split), ['lines' => [
        ['method' => 'cash', 'amount' => $half],
        ['method' => 'rocket', 'amount' => $half, 'reference' => 'RKT-77'],
    ]])->assertRedirect();

    expect($split->payments()->where('method', 'rocket')->first()->status)->toBe(Payment::STATUS_PENDING_VERIFICATION)
        ->and($split->fresh()->status->value)->not->toBe('paid');

    $this->actingAs($this->cashier, 'staff')->post(route('admin.payments.single', posOrder($this)), ['method' => 'rocket'])
        ->assertStatus(302);
});

it('stores cash suggestions per branch with sensible defaults', function (): void {
    expect(PosSettings::for($this->branch)['cash_suggestions'])->toBe([1000, 500, 200, 100]);

    $this->actingAs($this->owner, 'staff')->put(route('admin.settings.branch', $this->branch), branchSettingsPayload([
        'pos' => ['cash_suggestions' => [2000, 1000, 500]],
    ]))->assertRedirect();

    expect(PosSettings::for($this->branch->fresh())['cash_suggestions'])->toBe([2000, 1000, 500]);

    $this->actingAs($this->owner, 'staff')->put(route('admin.settings.branch', $this->branch), branchSettingsPayload([
        'pos' => ['cash_suggestions' => [0, 5000000]],
    ]))->assertSessionHasErrors(['pos.cash_suggestions.0', 'pos.cash_suggestions.1']);
});
