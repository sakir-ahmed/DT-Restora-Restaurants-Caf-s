<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Demo\RecordDemoEventsAction;
use App\Actions\Demo\StartDemoSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordDemoEventsRequest;
use App\Http\Requests\StartDemoSessionRequest;
use App\Services\DemoJourney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoSessionController extends Controller
{
    public function __construct(private readonly DemoJourney $journey) {}

    public function start(StartDemoSessionRequest $request, StartDemoSessionAction $action): JsonResponse
    {
        abort_unless(DemoJourney::enabled(), 404);

        $session = $action->execute($request, (string) $request->validated('mobile'));

        return response()
            ->json(['session' => $this->journey->describe($session)])
            ->withCookie(cookie(DemoJourney::COOKIE, $session->token, DemoJourney::COOKIE_MINUTES, '/', null, $request->isSecure(), true, false, 'lax'));
    }

    public function events(RecordDemoEventsRequest $request, RecordDemoEventsAction $action): JsonResponse
    {
        abort_unless(DemoJourney::enabled(), 404);

        $session = $this->journey->current($request);

        if ($session === null) {
            return response()->json(['recorded' => 0], 202);
        }

        /** @var list<array{type: string, name?: ?string, path?: ?string, meta?: ?array<string, mixed>, age_ms?: ?int}> $events */
        $events = $request->validated('events');

        return response()->json(['recorded' => $action->execute($session, $events)], 202);
    }

    public function end(Request $request): JsonResponse
    {
        abort_unless(DemoJourney::enabled(), 404);

        $token = $request->cookie(DemoJourney::COOKIE);

        if (is_string($token)) {
            $this->journey->forget($token);
        }

        return response()->json(['ok' => true])->withoutCookie(DemoJourney::COOKIE);
    }
}
