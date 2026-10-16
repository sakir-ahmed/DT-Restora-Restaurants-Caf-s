<?php

declare(strict_types=1);

use App\Jobs\SendSmsJob;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\SmsCampaignService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);

    $this->branch = Branch::factory()->create(['code' => 'BR01', 'online_ordering_enabled' => true]);
    $category = MenuCategory::factory()->create(['branch_id' => $this->branch->id]);
    $this->item = MenuItem::factory()->create([
        'branch_id' => $this->branch->id,
        'menu_category_id' => $category->id,
        'name' => 'Kacchi',
        'base_price' => '450.00',
        'description' => 'Slow cooked mutton',
    ]);
});

it('opens the customer sign-in page from the website', function (): void {
    $this->get('/customer/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('web/Login'));
});

it('renders a public item page for the branch', function (): void {
    $this->get('/menu/br01/items/'.$this->item->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('web/Item')
            ->where('item.name', 'Kacchi'));
});

it('hides an item that belongs to another branch', function (): void {
    $other = Branch::factory()->create(['code' => 'BR02']);

    $this->get('/menu/br02/items/'.$this->item->id)->assertNotFound();
    expect($other->id)->not->toBe($this->branch->id);
});

it('anonymizes a customer without deleting their orders', function (): void {
    $customer = Customer::query()->create([
        'name' => 'Mina',
        'mobile' => '8801711223344',
        'email' => 'mina@example.com',
    ]);

    $order = Order::query()->create([
        'branch_id' => $this->branch->id,
        'invoice_no' => 'BR01-WEB-TEST-0001',
        'order_type' => 'takeaway',
        'channel' => 'website',
        'status' => 'paid',
        'customer_id' => $customer->id,
        'subtotal' => '100.00',
        'total' => '100.00',
    ]);

    $this->actingAs($customer, 'customer')
        ->delete('/account')
        ->assertRedirect('/');

    $customer->refresh();

    expect($customer->name)->toBe('Deleted customer')
        ->and($customer->mobile)->toBeNull()
        ->and($customer->email)->toBeNull()
        ->and($customer->is_active)->toBeFalse()
        ->and(Order::query()->find($order->id)?->customer_id)->toBe($customer->id);
});

it('sends a birthday SMS once per year', function (): void {
    Queue::fake();

    Customer::query()->create([
        'name' => 'Rafi',
        'mobile' => '8801811223344',
        'birthday' => now()->toDateString(),
    ]);

    $campaigns = app(SmsCampaignService::class);

    expect($campaigns->sendBirthdayOffers())->toBe(1)
        ->and($campaigns->sendBirthdayOffers())->toBe(0);

    Queue::assertPushed(SendSmsJob::class, 1);
});

it('sends a win-back SMS to customers quiet for 30 days', function (): void {
    Queue::fake();

    $customer = Customer::query()->create([
        'name' => 'Nila',
        'mobile' => '8801911223344',
    ]);

    $order = Order::query()->create([
        'branch_id' => $this->branch->id,
        'invoice_no' => 'BR01-WEB-TEST-0002',
        'order_type' => 'delivery',
        'channel' => 'website',
        'status' => 'paid',
        'customer_id' => $customer->id,
        'subtotal' => '80.00',
        'total' => '80.00',
    ]);
    $order->forceFill(['created_at' => now()->subDays(40)])->save();

    expect(app(SmsCampaignService::class)->sendWinBack())->toBe(1);

    Queue::assertPushed(SendSmsJob::class, 1);
});

it('redirects customer Google login when it is not configured', function (): void {
    config(['services.google.client_id' => null]);

    $this->get('/customer/auth/google/redirect')
        ->assertRedirect(route('web.customer.login'));
});
