<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JobOpening extends Model
{
    use HasFactory;

    public const TYPES = ['full_time', 'part_time', 'contract', 'internship'];

    protected $fillable = [
        'branch_id', 'title', 'title_bn', 'department', 'employment_type',
        'summary', 'summary_bn', 'description', 'description_bn',
        'requirements', 'requirements_bn', 'is_active', 'closes_on',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'closes_on' => 'date',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<JobApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Visible on the website: switched on and not past its closing date.
     */
    public function scopeOpen(Builder $builder): Builder
    {
        return $builder
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('closes_on')->orWhereDate('closes_on', '>=', now()->toDateString()));
    }

    public function slug(): string
    {
        $slug = Str::slug($this->title);

        return $slug !== '' ? $slug : 'job';
    }

    /**
     * Pick the Bangla text of a bilingual field when the page is in Bangla.
     */
    public function text(string $field, string $locale): ?string
    {
        $bangla = $this->getAttribute($field.'_bn');

        return $locale === 'bn' && $bangla ? $bangla : $this->getAttribute($field);
    }
}
