<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([ChartOfAccountsSeeder::class, RoleSeeder::class]);
    Storage::fake('local');

    $this->branch = Branch::factory()->create(['name' => 'Gulshan Kitchen', 'code' => 'BR01', 'address' => 'House 4, Road 11', 'city' => 'Dhaka']);
    $this->opening = JobOpening::factory()->create([
        'title' => 'Line Cook',
        'branch_id' => $this->branch->id,
        'closes_on' => now()->addMonth()->toDateString(),
    ]);
});

function jobGraph(string $html): array
{
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return json_decode($matches[1] ?? '{}', true)['@graph'] ?? [];
}

it('lists only open positions on the careers page', function (): void {
    JobOpening::factory()->inactive()->create(['title' => 'Switched Off']);
    JobOpening::factory()->expired()->create(['title' => 'Past Deadline']);

    $response = $this->get('/careers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('web/careers/Index')->has('openings', 1)->where('openings.0.title', 'Line Cook'));

    expect($response->getContent())->not->toContain('Switched Off')->not->toContain('Past Deadline');
});

it('publishes each job with Google for Jobs structured data', function (): void {
    $url = '/careers/'.$this->opening->id.'/line-cook';

    $response = $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('web/careers/Show')->where('opening.title', 'Line Cook'));
    $posting = collect(jobGraph($response->getContent()))->firstWhere('@type', 'JobPosting');

    expect($posting['title'])->toBe('Line Cook')
        ->and($posting['employmentType'])->toBe('FULL_TIME')
        ->and($posting['validThrough'])->toBe($this->opening->closes_on->toDateString())
        ->and($posting['hiringOrganization']['name'])->toBe(config('app.name'))
        ->and($posting['jobLocation']['address']['streetAddress'])->toBe('House 4, Road 11');
});

it('redirects an old or missing job slug to the canonical address', function (): void {
    $this->get('/careers/'.$this->opening->id)->assertRedirect('/careers/'.$this->opening->id.'/line-cook')->assertStatus(301);
    $this->get('/careers/'.$this->opening->id.'/old-title')->assertRedirect('/careers/'.$this->opening->id.'/line-cook');
});

it('hides closed and unknown jobs', function (): void {
    $expired = JobOpening::factory()->expired()->create(['title' => 'Old Job']);
    $inactive = JobOpening::factory()->inactive()->create(['title' => 'Paused Job']);

    $this->get('/careers/'.$expired->id.'/old-job')->assertNotFound();
    $this->get('/careers/'.$inactive->id.'/paused-job')->assertNotFound();
    $this->get('/careers/99999/nothing')->assertNotFound();
});

it('stores an application with a CV outside the public folder', function (): void {
    $this->post('/careers/apply', [
        'job_opening_id' => $this->opening->id,
        'name' => 'Rahim Uddin',
        'mobile' => '01711223344',
        'email' => 'rahim@example.com',
        'message' => 'Five years in busy kitchens.',
        'cv' => UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'),
    ])->assertSessionHasNoErrors()->assertSessionHas('success', __('site.apply_sent'));

    $application = JobApplication::query()->firstOrFail();

    expect($application->mobile)->toBe('8801711223344')
        ->and($application->job_opening_id)->toBe($this->opening->id)
        ->and($application->status)->toBe('new')
        ->and($application->cv_path)->toStartWith('cv/');

    Storage::disk('local')->assertExists($application->cv_path);
});

it('accepts a general application without a role', function (): void {
    $this->post('/careers/apply', ['name' => 'Salma', 'mobile' => '01911223344'])->assertSessionHasNoErrors();

    expect(JobApplication::query()->first()->job_opening_id)->toBeNull();
});

it('explains every problem with an application', function (): void {
    $this->post('/careers/apply', ['name' => '', 'mobile' => '123', 'email' => 'nope'])
        ->assertSessionHasErrors([
            'name' => __('site.err_name'),
            'mobile' => __('site.err_mobile'),
            'email' => __('site.err_email'),
        ]);

    $this->post('/careers/apply', ['name' => 'A', 'mobile' => '01711223344', 'cv' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors(['cv' => __('site.err_cv_type')]);

    $this->post('/careers/apply', ['name' => 'A', 'mobile' => '01711223344', 'cv' => UploadedFile::fake()->create('big.pdf', 3000, 'application/pdf')])
        ->assertSessionHasErrors(['cv' => __('site.err_cv_size')]);

    $closed = JobOpening::factory()->inactive()->create();

    $this->post('/careers/apply', ['name' => 'A', 'mobile' => '01711223344', 'job_opening_id' => $closed->id])
        ->assertSessionHasErrors(['job_opening_id' => __('site.err_job_closed')]);

    expect(JobApplication::query()->count())->toBe(0);
});

it('silently drops bot submissions that fill the hidden field', function (): void {
    $this->post('/careers/apply', ['name' => 'Bot', 'mobile' => '01711223344', 'website' => 'http://spam.example'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(JobApplication::query()->count())->toBe(0);
});

it('rate limits the application form', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->post('/careers/apply', ['name' => 'Person '.$attempt, 'mobile' => '01711223344'])->assertSessionHasNoErrors();
    }

    $this->post('/careers/apply', ['name' => 'Too many', 'mobile' => '01711223344'])->assertStatus(429);
});

it('lets an owner manage openings and review applications', function (): void {
    $owner = User::factory()->role('owner')->create();

    $payload = [
        'title' => 'Barista',
        'employment_type' => 'part_time',
        'summary' => 'Make great coffee.',
        'description' => 'Prepare drinks.',
        'is_active' => true,
    ];

    $this->actingAs($owner, 'staff')->get('/admin/careers')->assertOk()->assertInertia(fn ($page) => $page->component('admin/careers/Index')->has('openings', 1));
    $this->actingAs($owner, 'staff')->post('/admin/careers', $payload)->assertSessionHasNoErrors();

    $barista = JobOpening::query()->where('title', 'Barista')->firstOrFail();

    $this->actingAs($owner, 'staff')->put('/admin/careers/'.$barista->id, [...$payload, 'is_active' => false])->assertSessionHasNoErrors();
    expect($barista->fresh()->is_active)->toBeFalse();

    $this->actingAs($owner, 'staff')->post('/admin/careers', [...$payload, 'title' => ''])->assertSessionHasErrors('title');
    $this->actingAs($owner, 'staff')->post('/admin/careers', [...$payload, 'employment_type' => 'volunteer'])->assertSessionHasErrors('employment_type');

    Storage::disk('local')->put('cv/test.pdf', 'pdf');
    $application = JobApplication::factory()->create(['job_opening_id' => $this->opening->id, 'cv_path' => 'cv/test.pdf']);

    $this->actingAs($owner, 'staff')->put('/admin/careers/applications/'.$application->id, ['status' => 'shortlisted'])->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe('shortlisted');

    $this->actingAs($owner, 'staff')->put('/admin/careers/applications/'.$application->id, ['status' => 'bogus'])->assertSessionHasErrors('status');

    $this->actingAs($owner, 'staff')->get('/admin/careers/applications/'.$application->id.'/cv')->assertOk()->assertDownload('cv-'.$application->id.'.pdf');

    $this->actingAs($owner, 'staff')->delete('/admin/careers/applications/'.$application->id)->assertSessionHas('success');
    Storage::disk('local')->assertMissing('cv/test.pdf');

    $this->actingAs($owner, 'staff')->delete('/admin/careers/'.$barista->id);
    expect(JobOpening::query()->find($barista->id))->toBeNull();
});

it('keeps careers admin and CVs away from other roles and visitors', function (): void {
    $manager = User::factory()->role('branch-manager')->create(['branch_id' => $this->branch->id]);
    Storage::disk('local')->put('cv/secret.pdf', 'pdf');
    $application = JobApplication::factory()->create(['cv_path' => 'cv/secret.pdf']);

    $this->actingAs($manager, 'staff')->get('/admin/careers')->assertForbidden();
    $this->actingAs($manager, 'staff')->get('/admin/careers/applications/'.$application->id.'/cv')->assertForbidden();
});

it('keeps CVs away from visitors and the public storage folder', function (): void {
    Storage::disk('local')->put('cv/secret.pdf', 'pdf');
    $application = JobApplication::factory()->create(['cv_path' => 'cv/secret.pdf']);

    $this->get('/admin/careers/applications/'.$application->id.'/cv')->assertRedirect();
    expect($this->get('/storage/cv/secret.pdf')->getStatusCode())->toBeIn([403, 404]);
});

it('serves every policy page with complete text in both languages', function (string $url, string $key, int $sections): void {
    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('web/Legal')->where('page', $key)->where('sections', $sections));

    foreach (['en', 'bn'] as $locale) {
        foreach (range(1, $sections) as $number) {
            foreach (['title', 'body'] as $part) {
                $text = __("legal.{$key}.s{$number}_{$part}", [], $locale);

                expect($text)->not->toBe("legal.{$key}.s{$number}_{$part}")->and(strlen($text))->toBeGreaterThan($part === 'title' ? 3 : 30);
            }
        }
    }
})->with([
    'privacy' => ['/privacy', 'privacy', 9],
    'terms' => ['/terms', 'terms', 10],
    'refund' => ['/refund-policy', 'refund', 6],
    'delivery' => ['/delivery-policy', 'delivery', 6],
    'cookies' => ['/cookie-policy', 'cookies', 5],
]);

it('has no section beyond what each policy page prints', function (string $key, int $sections): void {
    expect(__("legal.{$key}.s".($sections + 1).'_title'))->toBe("legal.{$key}.s".($sections + 1).'_title');
})->with([['privacy', 9], ['terms', 10], ['refund', 6], ['delivery', 6], ['cookies', 5]]);

it('answers ten questions on the FAQ page with matching structured data', function (): void {
    $response = $this->get('/faq')->assertOk()->assertInertia(fn ($page) => $page->component('web/Faq'));
    $faq = collect(jobGraph($response->getContent()))->firstWhere('@type', 'FAQPage');

    expect($faq['mainEntity'])->toHaveCount(10)
        ->and($faq['mainEntity'][9]['name'])->toBe(__('site.faq.q10'));

    $home = collect(jobGraph($this->get('/')->getContent()))->firstWhere('@type', 'FAQPage');

    expect($home['mainEntity'])->toHaveCount(6);
});

it('shares the legal text with the website footer and lists new pages in the sitemap', function (): void {
    JobOpening::factory()->expired()->create(['title' => 'Old Job']);

    expect($this->get('/')->viewData('page')['props']['translations'])->toHaveKey('legal.privacy.title');

    $xml = $this->get('/sitemap.xml')->getContent();

    foreach (['web.careers.index', 'web.faq', 'web.delivery', 'web.refund', 'web.cookies', 'web.privacy', 'web.terms', 'web.gallery'] as $name) {
        expect($xml)->toContain('<loc>'.route($name).'</loc>');
    }

    expect($xml)->toContain('/careers/'.$this->opening->id.'/line-cook')->not->toContain('old-job');
});
