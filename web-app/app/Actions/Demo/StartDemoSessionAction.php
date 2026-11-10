<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Enums\DemoEventType;
use App\Enums\DemoStage;
use App\Models\DemoSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StartDemoSessionAction
{
    public function __construct(private readonly RecordDemoEventsAction $record) {}

    public function execute(Request $request, string $mobile): DemoSession
    {
        $agent = (string) $request->userAgent();
        $referrer = $this->host($request->headers->get('referer'));

        $session = DemoSession::query()->create([
            'token' => Str::random(40),
            'mobile' => normalize_bd_mobile($mobile),
            'locale' => app()->getLocale(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit($agent, 250, ''),
            'device' => preg_match('/Mobi|Android|iPhone/i', $agent) === 1 ? 'mobile' : 'desktop',
            'referrer' => $referrer,
            'utm_source' => $this->cleanTag($request->input('utm_source')),
            'utm_campaign' => $this->cleanTag($request->input('utm_campaign')),
            'stage' => DemoStage::Visited->value,
            'stage_rank' => DemoStage::Visited->rank(),
            'consented' => true,
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->record->execute($session, [[
            'type' => DemoEventType::SessionStart->value,
            'path' => '/',
            'name' => null,
            'meta' => null,
            'age_ms' => 0,
        ]]);

        return $session;
    }

    private function host(?string $url): ?string
    {
        $host = $url !== null ? parse_url($url, PHP_URL_HOST) : null;

        return is_string($host) && $host !== $this->ownHost() ? Str::limit($host, 250, '') : null;
    }

    private function ownHost(): ?string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }

    private function cleanTag(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Str::limit(preg_replace('/[^A-Za-z0-9_\-. ]/', '', $value) ?? '', 100, '');
    }
}
