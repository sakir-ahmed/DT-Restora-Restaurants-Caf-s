<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayBasis;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'user_id', 'name', 'name_bn', 'designation',
        'mobile', 'photo_path', 'device_user_id', 'monthly_salary', 'pay_basis', 'pay_rate',
        'joined_at', 'is_active',
    ];

    protected $appends = ['photo_url'];

    protected $casts = [
        'monthly_salary' => 'decimal:2',
        'pay_rate' => 'decimal:2',
        'pay_basis' => PayBasis::class,
        'joined_at' => 'date',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StaffAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    /** @return HasMany<SalaryAdvance, $this> */
    public function salaryAdvances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    /**
     * Public URL of the staff photo, or null when none is set.
     *
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo_path ? '/storage/'.ltrim($this->photo_path, '/') : null);
    }

    public function displayName(string $locale): string
    {
        return $locale === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }

    /**
     * Keep the monthly salary column aligned with the pay basis so the
     * existing daily allocation still reads monthly_salary.
     *
     * @param  array<string, mixed>  $input
     * @return array{pay_basis: string, pay_rate: string, monthly_salary: string, device_user_id?: string|null}
     */
    public static function payAttributes(array $input): array
    {
        $basis = (string) ($input['pay_basis'] ?? PayBasis::Monthly->value);

        if (! in_array($basis, array_column(PayBasis::cases(), 'value'), true)) {
            $basis = PayBasis::Monthly->value;
        }

        $hasRate = array_key_exists('pay_rate', $input) && $input['pay_rate'] !== null && $input['pay_rate'] !== '';
        $hasMonthly = array_key_exists('monthly_salary', $input) && $input['monthly_salary'] !== null && $input['monthly_salary'] !== '';

        if ($basis === PayBasis::Monthly->value) {
            $rate = $hasRate ? $input['pay_rate'] : ($hasMonthly ? $input['monthly_salary'] : 0);
            $monthly = $rate;
        } else {
            $rate = $hasRate ? $input['pay_rate'] : 0;
            $monthly = 0;
        }

        $attributes = [
            'pay_basis' => $basis,
            'pay_rate' => number_format((float) $rate, 2, '.', ''),
            'monthly_salary' => number_format((float) $monthly, 2, '.', ''),
        ];

        if (array_key_exists('device_user_id', $input)) {
            $deviceUserId = trim((string) $input['device_user_id']);
            $attributes['device_user_id'] = $deviceUserId === '' ? null : $deviceUserId;
        }

        return $attributes;
    }

    public function rateAmount(): string
    {
        if ($this->pay_basis === PayBasis::Monthly) {
            return (string) $this->monthly_salary;
        }

        return (string) $this->pay_rate;
    }
}
