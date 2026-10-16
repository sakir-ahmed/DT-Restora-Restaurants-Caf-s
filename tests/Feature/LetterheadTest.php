<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Letter;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->branch = Branch::factory()->create([
        'name' => 'Gulshan Kitchen',
        'phone' => '01711000001',
        'address' => 'Road 12, Gulshan',
        'city' => 'Dhaka',
    ]);
    $this->accountant = User::factory()->role('accountant')->create();
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();
});

it('lets a manager write, edit, and print a letter', function (): void {
    $payload = [
        'title' => 'Leave approval',
        'recipient' => 'Karim Ahmed',
        'subject' => 'Two days leave',
        'body' => 'Your leave for 20 October is approved.',
        'letter_date' => '2026-10-16',
    ];

    $this->actingAs($this->manager, 'staff')
        ->get('/admin/letters')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/letters/Index'));

    $this->actingAs($this->manager, 'staff')
        ->post('/admin/letters', $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $letter = Letter::query()->firstOrFail();

    expect($letter->title)->toBe('Leave approval')
        ->and($letter->body)->toBe('Your leave for 20 October is approved.')
        ->and($letter->branch_id)->toBe($this->branch->id);

    $this->actingAs($this->manager, 'staff')
        ->put('/admin/letters/'.$letter->id, [...$payload, 'body' => 'Leave is confirmed for two days.'])
        ->assertSessionHasNoErrors();

    $print = $this->actingAs($this->manager, 'staff')->get('/admin/letters/'.$letter->id.'/print');

    $print->assertOk();
    expect($print->headers->get('content-type'))->toContain('pdf')
        ->and(str_starts_with($print->getContent(), '%PDF'))->toBeTrue()
        ->and($letter->fresh()->body)->toBe('Leave is confirmed for two days.');

    $html = view('letterhead.document', [
        'branchName' => 'Gulshan Kitchen',
        'address' => 'Road 12, Gulshan, Dhaka',
        'phone' => '01711000001',
        'logo' => null,
        'letter' => [
            'title' => $letter->title,
            'recipient' => $letter->recipient,
            'subject' => $letter->subject,
            'body' => $letter->fresh()->body,
            'date' => '16 Oct 2026',
        ],
        'slip' => null,
        'printedAt' => '16 Oct 2026',
    ])->render();

    expect($html)->toContain('Leave is confirmed for two days.')
        ->and($html)->toContain('Karim Ahmed');

    $this->actingAs($this->manager, 'staff')
        ->delete('/admin/letters/'.$letter->id)
        ->assertRedirect();

    expect(Letter::query()->count())->toBe(0);
});

it('prints a salary slip from payroll', function (): void {
    $employee = Employee::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Slip Chef',
        'pay_basis' => 'daily',
        'pay_rate' => '800.00',
        'monthly_salary' => '0.00',
    ]);

    $slip = $this->actingAs($this->accountant, 'staff')
        ->get('/admin/payroll/slips/'.$employee->id.'?from=2026-10-01&to=2026-10-16');

    $slip->assertOk();
    expect($slip->headers->get('content-type'))->toContain('pdf')
        ->and(str_starts_with($slip->getContent(), '%PDF'))->toBeTrue();
});

it('forbids a cashier from letters and hides another branch letter', function (): void {
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();
    $other = Branch::factory()->create();
    $letter = Letter::factory()->create(['branch_id' => $other->id]);

    $this->actingAs($cashier, 'staff')->get('/admin/letters')->assertForbidden();
    $this->actingAs($cashier, 'staff')->get('/admin/letters/'.$letter->id.'/print')->assertForbidden();
    $this->actingAs($this->manager, 'staff')->get('/admin/letters/'.$letter->id.'/print')->assertNotFound();
});
