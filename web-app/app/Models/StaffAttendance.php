<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\StaffAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    /** @use HasFactory<StaffAttendanceFactory> */
    use BelongsToBranch, HasFactory;

    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_DEVICE = 'device';

    protected $fillable = [
        'branch_id', 'employee_id', 'work_date', 'status',
        'clock_in', 'clock_out', 'source', 'attendance_device_id', 'recorded_by',
    ];

    protected $casts = [
        'work_date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<AttendanceDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function hours(): float
    {
        if ($this->clock_in === null || $this->clock_out === null) {
            return 0.0;
        }

        $seconds = $this->clock_out->getTimestamp() - $this->clock_in->getTimestamp();

        return round(max(0, $seconds) / 3600, 2);
    }
}
