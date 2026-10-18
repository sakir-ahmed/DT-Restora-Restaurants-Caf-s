<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Enums\DemoEventType;
use App\Enums\DemoStage;
use App\Models\DemoEvent;
use App\Models\DemoSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordDemoEventsAction
{
    private const MAX_META_BYTES = 1000;

    /**
     * Store a batch of journey events and move the session forward in the funnel.
     *
     * @param  list<array{type: string, name?: ?string, path?: ?string, meta?: ?array<string, mixed>, age_ms?: ?int}>  $events
     */
    public function execute(DemoSession $session, array $events): int
    {
        $rows = [];
        $stage = DemoStage::tryFrom((string) $session->stage) ?? DemoStage::Visited;
        $pageViews = 0;
        $now = now();

        foreach ($events as $event) {
            $type = DemoEventType::tryFrom($event['type']);

            if ($type === null) {
                continue;
            }

            $reached = $type->stage();

            if ($reached !== null && $reached->rank() > $stage->rank()) {
                $stage = $reached;
            }

            if (in_array($type, [DemoEventType::PageView, DemoEventType::MenuView, DemoEventType::ItemView, DemoEventType::CheckoutStart, DemoEventType::AdminView], true)) {
                $pageViews++;
            }

            $rows[] = [
                'demo_session_id' => $session->id,
                'type' => $type->value,
                'name' => isset($event['name']) ? Str::limit(strip_tags($event['name']), 118, '') : null,
                'path' => isset($event['path']) ? Str::limit($this->cleanPath($event['path']), 198, '') : null,
                'meta' => $this->encodeMeta($event['meta'] ?? null),
                'occurred_at' => $now->copy()->subMilliseconds(min(max((int) ($event['age_ms'] ?? 0), 0), 3_600_000)),
            ];
        }

        if ($rows === []) {
            return 0;
        }

        DB::transaction(function () use ($session, $rows, $stage, $pageViews, $now): void {
            DemoEvent::query()->insert($rows);

            DemoSession::query()->whereKey($session->id)->update([
                'events_count' => DB::raw('events_count + '.count($rows)),
                'page_views' => DB::raw('page_views + '.$pageViews),
                'last_seen_at' => $now,
            ]);

            // The session row may come from a short-lived cache, so only ever move the stage forward.
            DemoSession::query()
                ->whereKey($session->id)
                ->where('stage_rank', '<', $stage->rank())
                ->update(['stage' => $stage->value, 'stage_rank' => $stage->rank()]);
        });

        return count($rows);
    }

    private function cleanPath(string $path): string
    {
        return '/'.ltrim(explode('?', explode('#', $path)[0])[0], '/');
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function encodeMeta(?array $meta): ?string
    {
        if ($meta === null || $meta === []) {
            return null;
        }

        $clean = [];

        foreach (array_slice($meta, 0, 8, true) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $clean[Str::limit((string) $key, 30, '')] = is_string($value) ? Str::limit(strip_tags($value), 100, '') : $value;
            }
        }

        $json = json_encode($clean, JSON_UNESCAPED_UNICODE);

        return $json !== false && strlen($json) <= self::MAX_META_BYTES ? $json : null;
    }
}
