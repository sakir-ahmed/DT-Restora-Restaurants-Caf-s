<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A photo or video link shown on the website. It belongs to a branch (outlet
 * photos), a menu item (dish photos) or, with no owner, the general gallery.
 */
class Media extends Model
{
    use HasFactory;

    public const KIND_IMAGE = 'image';

    public const KIND_VIDEO = 'video';

    protected $table = 'media';

    protected $fillable = [
        'mediable_type', 'mediable_id', 'kind', 'path', 'video_url',
        'title', 'title_bn', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return MorphTo<Model, $this> */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive(Builder $builder): Builder
    {
        return $builder->where('is_active', true);
    }

    public function scopeOrdered(Builder $builder): Builder
    {
        return $builder->orderBy('sort_order')->orderBy('id');
    }

    public function isVideo(): bool
    {
        return $this->kind === self::KIND_VIDEO;
    }

    /**
     * Address for the page itself. Relative, so it works on any host or port.
     */
    public function src(): ?string
    {
        return $this->path ? '/storage/'.ltrim($this->path, '/') : null;
    }

    /**
     * Full address for structured data and social previews, which need one.
     */
    public function absoluteSrc(): ?string
    {
        return $this->src() !== null ? url($this->src()) : null;
    }

    /**
     * Turn a pasted video link into something safe to embed. Only YouTube,
     * Vimeo, Facebook and direct video files are accepted; anything else is
     * rejected so a link can never load an unknown site inside the page.
     *
     * @return array{provider: string, embed_url: string, thumbnail: string|null}|null
     */
    public static function parseVideo(string $url): ?array
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return null;
        }

        $host = preg_replace('/^(www|m)\./', '', strtolower($parts['host']));
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        if (in_array($host, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], true)) {
            $id = match (true) {
                $host === 'youtu.be' => trim($path, '/'),
                str_starts_with($path, '/shorts/'), str_starts_with($path, '/embed/'), str_starts_with($path, '/live/') => explode('/', trim($path, '/'))[1] ?? '',
                default => (string) ($query['v'] ?? ''),
            };

            if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id) !== 1) {
                return null;
            }

            return [
                'provider' => 'youtube',
                'embed_url' => 'https://www.youtube-nocookie.com/embed/'.$id,
                'thumbnail' => 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg',
            ];
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
            if (preg_match('#/(\d{5,12})(?:/|$)#', $path, $match) !== 1) {
                return null;
            }

            return ['provider' => 'vimeo', 'embed_url' => 'https://player.vimeo.com/video/'.$match[1], 'thumbnail' => null];
        }

        if (in_array($host, ['facebook.com', 'fb.watch'], true)) {
            $isVideo = $host === 'fb.watch' || preg_match('#/(videos|watch|reel)(/|$)#', $path) === 1;

            if (! $isVideo) {
                return null;
            }

            return [
                'provider' => 'facebook',
                'embed_url' => 'https://www.facebook.com/plugins/video.php?show_text=false&href='.rawurlencode(trim($url)),
                'thumbnail' => null,
            ];
        }

        if (preg_match('/\.(mp4|webm|ogg)$/i', $path) === 1) {
            return ['provider' => 'file', 'embed_url' => trim($url), 'thumbnail' => null];
        }

        return null;
    }

    /**
     * Shape used by the public pages and the admin grid.
     *
     * @return array<string, mixed>
     */
    public function toDisplay(string $locale): array
    {
        $video = $this->isVideo() && $this->video_url ? self::parseVideo($this->video_url) : null;

        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'src' => $this->src(),
            'absolute_src' => $this->absoluteSrc(),
            'title' => $locale === 'bn' && $this->title_bn ? $this->title_bn : $this->title,
            'title_en' => $this->title,
            'title_bn' => $this->title_bn,
            'video_url' => $this->video_url,
            'provider' => $video['provider'] ?? null,
            'embed_url' => $video['embed_url'] ?? null,
            'thumbnail' => $video['thumbnail'] ?? null,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
