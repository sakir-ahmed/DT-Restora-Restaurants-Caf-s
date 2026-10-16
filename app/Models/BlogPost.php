<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\BlogMarkdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'author_id', 'slug', 'title', 'title_bn', 'category', 'excerpt', 'excerpt_bn',
        'body', 'body_bn', 'cover_path', 'cover_alt', 'status', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Visible on the website: published and not scheduled for later.
     */
    public function scopePublished(Builder $builder): Builder
    {
        return $builder
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublic(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /**
     * A unique, readable address segment built from the English title.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $counter = 2;

        while (static::query()->where('slug', $slug)->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /**
     * Pick the Bangla text of a bilingual field when the page is in Bangla.
     */
    public function text(string $field, string $locale): ?string
    {
        $bangla = $this->getAttribute($field.'_bn');

        return $locale === 'bn' && $bangla ? $bangla : $this->getAttribute($field);
    }

    public function html(string $locale): string
    {
        return app(BlogMarkdown::class)->toHtml((string) $this->text('body', $locale));
    }

    /**
     * Estimated minutes to read, at about 200 words a minute. Words are counted
     * by whitespace so it also works for Bangla.
     */
    public function readingMinutes(string $locale): int
    {
        $words = count(preg_split('/\s+/u', trim((string) $this->text('body', $locale)), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return max(1, (int) ceil($words / 200));
    }

    public function coverSrc(): ?string
    {
        return $this->cover_path ? '/storage/'.ltrim($this->cover_path, '/') : null;
    }

    public function coverAbsoluteSrc(): ?string
    {
        return $this->coverSrc() !== null ? url($this->coverSrc()) : null;
    }

    public function url(): string
    {
        return route('web.blog.show', ['slug' => $this->slug]);
    }
}
