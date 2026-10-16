<?php

declare(strict_types=1);

use App\Models\AttendancePunch;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\StaffAttendance;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->travelTo('2026-10-16 15:00:00');

    $this->branch = Branch::factory()->create(['code' => 'BR01']);
    $this->manager = User::factory()->role('branch-manager', $this->branch->id)->create();
    $this->employee = Employee::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Karim Uddin',
        'device_user_id' => '12',
    ]);
});

it('records manual attendance for a staff member', function (): void {
    $response = $this->actingAs($this->manager, 'staff')->post('/admin/attendance', [
        'employee_id' => $this->employee->id,
        'work_date' => '2026-10-16',
        'status' => 'present',
        'clock_in' => '2026-10-16 09:00:00',
        'clock_out' => '2026-10-16 13:00:00',
    ]);

    $response->assertRedirect();

    $attendance = StaffAttendance::query()->where('employee_id', $this->employee->id)->firstOrFail();

    expect($attendance->status)->toBe('present')
        ->and($attendance->source)->toBe('manual')
        ->and($attendance->hours())->toBe(4.0)
        ->and($attendance->branch_id)->toBe($this->branch->id);

    $page = $this->actingAs($this->manager, 'staff')->get('/admin/attendance?date=2026-10-16');
    $rows = $page->viewData('page')['props']['rows'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['employee'])->toBe('Karim Uddin')
        ->and($rows[0]['hours'])->toBe('4.00');
});

it('rejects a future date and an overlapping shift', function (): void {
    $future = $this->actingAs($this->manager, 'staff')->post('/admin/attendance', [
        'employee_id' => $this->employee->id,
        'work_date' => '2026-10-17',
        'status' => 'present',
        'clock_in' => '2026-10-17 09:00:00',
    ]);

    $future->assertSessionHasErrors([
        'work_date' => __('attendance.future_date'),
    ]);

    $this->actingAs($this->manager, 'staff')->post('/admin/attendance', [
        'employee_id' => $this->employee->id,
        'work_date' => '2026-10-15',
        'status' => 'present',
        'clock_in' => '2026-10-15 22:00:00',
        'clock_out' => '2026-10-16 02:00:00',
    ])->assertRedirect();

    $overlap = $this->actingAs($this->manager, 'staff')->post('/admin/attendance', [
        'employee_id' => $this->employee->id,
        'work_date' => '2026-10-16',
        'status' => 'present',
        'clock_in' => '2026-10-16 01:00:00',
        'clock_out' => '2026-10-16 08:00:00',
    ]);

    $overlap->assertSessionHasErrors([
        'clock_in' => __('attendance.overlap'),
    ]);

    expect(StaffAttendance::query()->count())->toBe(1);
});

it('forbids a cashier from recording attendance', function (): void {
    $cashier = User::factory()->role('cashier', $this->branch->id)->create();

    $this->actingAs($cashier, 'staff')->get('/admin/attendance')->assertForbidden();

    $this->actingAs($cashier, 'staff')->post('/admin/attendance', [
        'employee_id' => $this->employee->id,
        'work_date' => '2026-10-16',
        'status' => 'absent',
    ])->assertForbidden();

    expect(StaffAttendance::query()->count())->toBe(0);
});

it('maps a device punch onto attendance and ignores the same punch', function (): void {
    $created = $this->actingAs($this->manager, 'staff')->post('/admin/attendance/devices', [
        'name' => 'Front door',
        'serial' => 'ZK-100',
        'is_active' => true,
    ]);

    $created->assertRedirect()->assertSessionHas('device_token');
    $token = session('device_token');

    $payload = [
        'device_user_id' => '12',
        'punched_at' => '2026-10-16T09:00:00+06:00',
    ];

    $first = $this->postJson('/api/v1/attendance/punches', $payload, [
        'Authorization' => 'Bearer '.$token,
    ]);

    $second = $this->postJson('/api/v1/attendance/punches', $payload, [
        'Authorization' => 'Bearer '.$token,
    ]);

    $first->assertOk();
    $second->assertOk()
        ->assertJsonPath('data.attendance_id', $first->json('data.attendance_id'))
        ->assertJsonPath('data.employee_id', $this->employee->id);

    expect(AttendancePunch::query()->count())->toBe(1)
        ->and(StaffAttendance::query()->count())->toBe(1)
        ->and(StaffAttendance::query()->first()->source)->toBe('device')
        ->and(StaffAttendance::query()->first()->clock_in)->not->toBeNull()
        ->and(StaffAttendance::query()->first()->clock_out)->toBeNull();

    $this->postJson('/api/v1/attendance/punches', [
        'device_user_id' => '12',
        'punched_at' => '2026-10-16T13:00:00+06:00',
        'direction' => 'out',
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    expect(StaffAttendance::query()->count())->toBe(1)
        ->and(StaffAttendance::query()->first()->hours())->toBe(4.0);
});

it('rejects an unknown device user and a missing token', function (): void {
    $created = $this->actingAs($this->manager, 'staff')->post('/admin/attendance/devices', [
        'name' => 'Kitchen',
        'ip_address' => '192.168.1.40',
        'is_active' => true,
    ]);

    $token = session('device_token');

    $this->postJson('/api/v1/attendance/punches', [
        'device_user_id' => '99',
        'punched_at' => '2026-10-16T09:00:00+06:00',
    ], [
        'Authorization' => 'Bearer '.$token,
    ])->assertUnprocessable()
        ->assertJsonPath('error.details.device_user_id.0', __('attendance.unknown_device_user'));

    $this->postJson('/api/v1/attendance/punches', [
        'device_user_id' => '12',
        'punched_at' => '2026-10-16T09:00:00+06:00',
    ])->assertUnauthorized();

    expect(StaffAttendance::query()->count())->toBe(0)
        ->and(AttendancePunch::query()->count())->toBe(0);

    expect($created->status())->toBe(302);
});
