<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AttendancePunchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    /** @use HasFactory<AttendancePunchFactory> */
    use HasFactory;

    protected $fillable = [
        'attendance_device_id', 'device_user_id', 'punched_at', 'direction',
        'employee_id', 'staff_attendance_id',
    ];

    protected $casts = [
        'punched_at' => 'datetime',
    ];

    /** @return BelongsTo<AttendanceDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<StaffAttendance, $this> */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(StaffAttendance::class, 'staff_attendance_id');
    }
}
