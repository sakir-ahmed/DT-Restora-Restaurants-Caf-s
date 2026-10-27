<?php

declare(strict_types=1);

use App\Actions\Sales\CreateOrderAction;
use App\Enums\OrderType;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Shift;
use App\Models\User;
use App\Services\InvoiceNumberService;
use App\Services\PosSettings;
use Database\Seeders\ChartOfAccountsSeeder;
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
