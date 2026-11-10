<?php

declare(strict_types=1);

use App\Models\BlogPost;
use App\Models\Branch;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\Letter;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, ChartOfAccountsSeeder::class]);

    $this->branch = Branch::factory()->create();
    $this->owner = User::factory()->role('owner')->create();
    $this->waiter = User::factory()->role('waiter', $this->branch->id)->create();
    $this->ownerToken = $this->owner->createToken('staff', ['staff'])->plainTextToken;
    $this->waiterToken = $this->waiter->createToken('staff', ['staff'])->plainTextToken;
});

it('creates, edits and deletes blog posts', function (): void {
    $created = $this->withToken($this->ownerToken)->postJson('/api/v1/blog-posts', [
        'title' => 'Our new menu',
        'excerpt' => 'Fresh dishes this season.',
        'body' => 'Long body text.',
        'status' => 'published',
    ])->assertCreated()->json('data');

    expect($created['slug'])->toBe('our-new-menu')
        ->and($created['is_live'])->toBeTrue();

    $this->withToken($this->ownerToken)->putJson('/api/v1/blog-posts/'.$created['id'], [
        'title' => 'Our new menu',
        'excerpt' => 'Fresh dishes this season.',
        'body' => 'Long body text.',
        'status' => 'draft',
    ])->assertOk()->assertJsonPath('data.is_live', false);

    $this->withToken($this->ownerToken)->getJson('/api/v1/blog-posts')
        ->assertOk()->assertJsonCount(1, 'data.posts');

    $this->withToken($this->ownerToken)->deleteJson('/api/v1/blog-posts/'.$created['id'])->assertOk();

    expect(BlogPost::query()->count())->toBe(0);
});

it('manages job openings and moves an applicant through the pipeline', function (): void {
    $opening = $this->withToken($this->ownerToken)->postJson('/api/v1/careers/openings', [
        'title' => 'Line cook',
        'employment_type' => 'full_time',
        'summary' => 'Cook on the line.',
        'description' => 'Prepare dishes during service.',
        'is_active' => true,
    ])->assertCreated()->json('data');

    $application = JobApplication::query()->create([
        'job_opening_id' => $opening['id'],
        'name' => 'Karim',
        'mobile' => '01711111111',
        'status' => 'new',
    ]);

    $listing = $this->withToken($this->ownerToken)->getJson('/api/v1/careers')->assertOk()->json('data');

    expect($listing['openings'])->toHaveCount(1)
        ->and($listing['applications'])->toHaveCount(1)
        ->and($listing['statuses'])->toContain('shortlisted');

    $this->withToken($this->ownerToken)->putJson('/api/v1/careers/applications/'.$application->id, ['status' => 'shortlisted'])
        ->assertOk()->assertJsonPath('data.status', 'shortlisted');

    $this->withToken($this->ownerToken)->putJson('/api/v1/careers/applications/'.$application->id, ['status' => 'bogus'])
        ->assertUnprocessable();

    $this->withToken($this->ownerToken)->deleteJson('/api/v1/careers/openings/'.$opening['id'])->assertOk();

    expect(JobOpening::query()->count())->toBe(0);
});

it('keeps letters per branch and renders a PDF', function (): void {
    $letter = $this->withToken($this->ownerToken)->postJson('/api/v1/letters', [
        'branch_id' => $this->branch->id,
        'title' => 'Employment confirmation',
        'recipient' => 'To whom it may concern',
        'body' => 'This confirms employment.',
        'letter_date' => '2026-10-28',
    ])->assertCreated()->json('data');

    $this->withToken($this->ownerToken)->getJson('/api/v1/letters?branch_id='.$this->branch->id)
        ->assertOk()->assertJsonCount(1, 'data');

    $this->withToken($this->ownerToken)->get('/api/v1/letters/'.$letter['id'].'/pdf')
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->withToken($this->ownerToken)->deleteJson('/api/v1/letters/'.$letter['id'])->assertOk();

    expect(Letter::query()->count())->toBe(0);
});

it('adds a video link to the general gallery and reorders it', function (): void {
    $first = $this->withToken($this->ownerToken)->postJson('/api/v1/media/videos', [
        'owner' => 'general',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Tour',
    ])->assertCreated()->json('data');

    $this->withToken($this->ownerToken)->postJson('/api/v1/media/videos', [
        'owner' => 'general',
        'video_url' => 'https://www.youtube.com/watch?v=9bZkp7q19f0',
    ])->assertCreated();

    $this->withToken($this->ownerToken)->postJson('/api/v1/media/'.$first['id'].'/move', ['direction' => 'down'])->assertOk();
    $this->withToken($this->ownerToken)->putJson('/api/v1/media/'.$first['id'], ['title' => 'Tour', 'is_active' => false])->assertOk();

    $list = $this->withToken($this->ownerToken)->getJson('/api/v1/media?owner=general')->assertOk()->json('data');

    expect($list['media'])->toHaveCount(2);

    $this->withToken($this->ownerToken)->deleteJson('/api/v1/media/'.$first['id'])->assertOk();

    expect(Media::query()->count())->toBe(1);
});

it('creates and updates branches and blocks deleting one with staff', function (): void {
    $created = $this->withToken($this->ownerToken)->postJson('/api/v1/branches', [
        'name' => 'Banani',
        'code' => 'BN01',
        'vat_percent' => 5,
        'service_charge_percent' => 5,
    ])->assertCreated()->json('data');

    $this->withToken($this->ownerToken)->putJson('/api/v1/branches/'.$created['id'], [
        'name' => 'Banani Outlet',
        'code' => 'BN01',
        'vat_percent' => 7.5,
        'service_charge_percent' => 5,
    ])->assertOk()->assertJsonPath('data.name', 'Banani Outlet');

    $this->withToken($this->ownerToken)->deleteJson('/api/v1/branches/'.$this->branch->id)
        ->assertStatus(409);

    $this->withToken($this->ownerToken)->getJson('/api/v1/branches')->assertOk()->assertJsonCount(2, 'data');
});

it('keeps content endpoints behind permissions', function (): void {
    foreach (['/api/v1/blog-posts', '/api/v1/careers', '/api/v1/letters'] as $path) {
        app('auth')->forgetGuards();

        $this->withToken($this->waiterToken)->getJson($path)->assertForbidden();
    }
});
