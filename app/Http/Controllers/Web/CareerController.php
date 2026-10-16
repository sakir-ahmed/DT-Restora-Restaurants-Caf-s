<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Services\SeoBuilder;
use App\Services\TurnstileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CareerController extends Controller
{
    private const JOB_TYPES = [
        'full_time' => 'FULL_TIME',
        'part_time' => 'PART_TIME',
        'contract' => 'CONTRACTOR',
        'internship' => 'INTERN',
    ];

    public function __construct(private readonly SeoBuilder $seo) {}

    public function index(): Response
    {
        $locale = app()->getLocale();

        $openings = JobOpening::query()->open()->with('branch:id,name,name_bn,city')->orderBy('title')->get();

        return Inertia::render('web/careers/Index', [
            'openings' => $openings->map(fn (JobOpening $opening): array => $this->summary($opening, $locale))->values(),
            'seo' => $this->seo->page(
                title: __('site.seo_careers_title'),
                description: __('site.seo_careers_description'),
                canonical: route('web.careers.index'),
                breadcrumbs: [
                    ['name' => __('site.nav_home'), 'url' => url('/')],
                    ['name' => __('site.nav_careers'), 'url' => route('web.careers.index')],
                ],
            ),
        ]);
    }

    public function show(JobOpening $opening, ?string $slug = null): Response|RedirectResponse
    {
        abort_unless(JobOpening::query()->open()->whereKey($opening->id)->exists(), 404);

        // One canonical address per job, even when the slug in the link is old or missing.
        if ($slug !== $opening->slug()) {
            return redirect()->route('web.careers.show', ['opening' => $opening->id, 'slug' => $opening->slug()], 301);
        }

        $locale = app()->getLocale();
        $opening->load('branch');
        $canonical = route('web.careers.show', ['opening' => $opening->id, 'slug' => $opening->slug()]);
        $branch = $opening->branch ?? Branch::query()->where('is_active', true)->orderBy('id')->first();

        $posting = array_filter([
            '@type' => 'JobPosting',
            'title' => $opening->title,
            'description' => trim($opening->description."\n\n".($opening->requirements ?? '')),
            'datePosted' => $opening->created_at->toDateString(),
            'validThrough' => $opening->closes_on?->toDateString(),
            'employmentType' => self::JOB_TYPES[$opening->employment_type] ?? 'FULL_TIME',
            'hiringOrganization' => ['@type' => 'Organization', 'name' => (string) config('app.name'), 'sameAs' => url('/')],
            'jobLocation' => $branch ? [
                '@type' => 'Place',
                'address' => array_filter([
                    '@type' => 'PostalAddress',
                    'streetAddress' => $branch->address,
                    'addressLocality' => $branch->city,
                    'addressCountry' => 'BD',
                ]),
            ] : null,
        ]);

        return Inertia::render('web/careers/Show', [
            'opening' => $this->summary($opening, $locale) + [
                'description' => $opening->text('description', $locale),
                'requirements' => $opening->text('requirements', $locale),
            ],
            'seo' => $this->seo->page(
                title: __('site.seo_job_title', ['job' => $opening->text('title', $locale)]),
                description: $opening->text('summary', $locale) ?? $opening->title,
                canonical: $canonical,
                jsonLd: [$posting],
                breadcrumbs: [
                    ['name' => __('site.nav_home'), 'url' => url('/')],
                    ['name' => __('site.nav_careers'), 'url' => route('web.careers.index')],
                    ['name' => $opening->text('title', $locale), 'url' => $canonical],
                ],
            ),
        ]);
    }

    public function apply(Request $request, TurnstileService $turnstile): RedirectResponse
    {
        $validated = $request->validate([
            'job_opening_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'string', 'regex:/^(\+?88)?01[3-9]\d{8}$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'message' => ['nullable', 'string', 'max:2000'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:2048'],
            'website' => ['nullable', 'string', 'max:500'],
            'turnstile_token' => ['nullable', 'string'],
        ], [
            'name.required' => __('site.err_name'),
            'mobile.required' => __('site.err_mobile'),
            'mobile.regex' => __('site.err_mobile'),
            'email.email' => __('site.err_email'),
            'cv.mimes' => __('site.err_cv_type'),
            'cv.max' => __('site.err_cv_size'),
        ]);

        // Bots fill the hidden "website" field; pretend it worked and store nothing.
        if (($validated['website'] ?? '') !== '') {
            return back()->with('success', __('site.apply_sent'));
        }

        if (! $turnstile->verify((string) ($validated['turnstile_token'] ?? ''))) {
            throw ValidationException::withMessages(['turnstile' => __('web.turnstile_failed')]);
        }

        $openingId = $validated['job_opening_id'] ?? null;

        if ($openingId !== null && ! JobOpening::query()->open()->whereKey($openingId)->exists()) {
            throw ValidationException::withMessages(['job_opening_id' => __('site.err_job_closed')]);
        }

        JobApplication::query()->create([
            'job_opening_id' => $openingId,
            'name' => $validated['name'],
            'mobile' => normalize_bd_mobile($validated['mobile']),
            'email' => $validated['email'] ?? null,
            'message' => $validated['message'] ?? null,
            'cv_path' => $request->file('cv')?->store('cv', 'local'),
        ]);

        return back()->with('success', __('site.apply_sent'));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(JobOpening $opening, string $locale): array
    {
        return [
            'id' => $opening->id,
            'slug' => $opening->slug(),
            'title' => $opening->text('title', $locale),
            'summary' => $opening->text('summary', $locale),
            'department' => $opening->department,
            'employment_type' => $opening->employment_type,
            'closes_on' => $opening->closes_on?->toDateString(),
            'location' => $opening->branch ? trim($opening->branch->displayName($locale).', '.($opening->branch->city ?? ''), ', ') : null,
        ];
    }
}
