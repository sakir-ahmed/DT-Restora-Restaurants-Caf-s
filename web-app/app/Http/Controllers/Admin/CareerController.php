<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\JobApplication;
use App\Models\JobOpening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CareerController extends Controller
{
    public function index(): Response
    {
        $this->authorize('careers.manage');

        return Inertia::render('admin/careers/Index', [
            'openings' => JobOpening::query()->withCount('applications')->orderByDesc('id')->get()->map(fn (JobOpening $opening): array => [
                ...$opening->only([
                    'id', 'branch_id', 'title', 'title_bn', 'department', 'employment_type',
                    'summary', 'summary_bn', 'description', 'description_bn',
                    'requirements', 'requirements_bn', 'is_active',
                ]),
                'closes_on' => $opening->closes_on?->toDateString(),
                'applications_count' => $opening->applications_count,
            ])->values(),
            'applications' => JobApplication::query()->with('opening:id,title')->latest()->limit(200)->get()->map(fn (JobApplication $application): array => [
                'id' => $application->id,
                'name' => $application->name,
                'mobile' => $application->mobile,
                'email' => $application->email,
                'message' => $application->message,
                'status' => $application->status,
                'has_cv' => $application->cv_path !== null,
                'job' => $application->opening?->title,
                'created_at' => $application->created_at->toIso8601String(),
            ])->values(),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'types' => JobOpening::TYPES,
            'statuses' => JobApplication::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('careers.manage');

        JobOpening::query()->create($this->validated($request));

        return back()->with('success', __('careers.created'));
    }

    public function update(Request $request, JobOpening $opening): RedirectResponse
    {
        $this->authorize('careers.manage');

        $opening->update($this->validated($request));

        return back()->with('success', __('careers.saved'));
    }

    public function destroy(JobOpening $opening): RedirectResponse
    {
        $this->authorize('careers.manage');

        $opening->delete();

        return back()->with('success', __('careers.deleted'));
    }

    public function updateApplication(Request $request, JobApplication $application): RedirectResponse
    {
        $this->authorize('careers.manage');

        $application->update($request->validate(['status' => ['required', Rule::in(JobApplication::STATUSES)]]));

        return back()->with('success', __('careers.saved'));
    }

    public function destroyApplication(JobApplication $application): RedirectResponse
    {
        $this->authorize('careers.manage');

        if ($application->cv_path !== null) {
            Storage::disk('local')->delete($application->cv_path);
        }

        $application->delete();

        return back()->with('success', __('careers.deleted'));
    }

    public function downloadCv(JobApplication $application): StreamedResponse
    {
        $this->authorize('careers.manage');

        abort_if($application->cv_path === null || ! Storage::disk('local')->exists($application->cv_path), 404);

        $extension = pathinfo($application->cv_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($application->cv_path, 'cv-'.$application->id.'.'.$extension);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'title' => ['required', 'string', 'max:150'],
            'title_bn' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', Rule::in(JobOpening::TYPES)],
            'summary' => ['required', 'string', 'max:300'],
            'summary_bn' => ['nullable', 'string', 'max:300'],
            'description' => ['required', 'string', 'max:8000'],
            'description_bn' => ['nullable', 'string', 'max:8000'],
            'requirements' => ['nullable', 'string', 'max:4000'],
            'requirements_bn' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['required', 'boolean'],
            'closes_on' => ['nullable', 'date'],
        ]);
    }
}
