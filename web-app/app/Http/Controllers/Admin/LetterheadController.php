<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LetterRequest;
use App\Models\Branch;
use App\Models\Letter;
use App\Services\LetterheadPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class LetterheadController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('letters.manage');

        $branch = $this->currentBranch($request);

        $letters = Letter::query()
            ->where('branch_id', $branch->id)
            ->latest('letter_date')
            ->latest('id')
            ->get()
            ->map(fn (Letter $letter): array => [
                'id' => $letter->id,
                'title' => $letter->title,
                'recipient' => $letter->recipient,
                'subject' => $letter->subject,
                'body' => $letter->body,
                'letter_date' => $letter->letter_date->toDateString(),
                'print_url' => route('admin.letters.print', $letter),
            ])
            ->values();

        return Inertia::render('admin/letters/Index', [
            'letters' => $letters,
        ]);
    }

    public function store(LetterRequest $request): RedirectResponse
    {
        $branch = $this->currentBranch($request);

        Letter::query()->create([
            ...$request->validated(),
            'branch_id' => $branch->id,
            'author_id' => $request->user('staff')?->id,
        ]);

        return back()->with('success', __('letterhead.saved'));
    }

    public function update(LetterRequest $request, Letter $letter): RedirectResponse
    {
        $this->letterInBranch($request, $letter);

        $letter->update($request->validated());

        return back()->with('success', __('letterhead.saved'));
    }

    public function destroy(Request $request, Letter $letter): RedirectResponse
    {
        $this->authorize('letters.manage');
        $this->letterInBranch($request, $letter);

        $letter->delete();

        return back()->with('success', __('letterhead.deleted'));
    }

    public function print(Request $request, Letter $letter, LetterheadPdf $pdf): HttpResponse
    {
        $this->authorize('letters.manage');
        $branch = $this->letterInBranch($request, $letter);

        return $pdf->stream($branch, [
            'letter' => [
                'title' => $letter->title,
                'recipient' => $letter->recipient,
                'subject' => $letter->subject,
                'body' => $letter->body,
                'date' => $letter->letter_date->format('d M Y'),
            ],
        ], 'letter-'.$letter->id.'.pdf');
    }

    private function letterInBranch(Request $request, Letter $letter): Branch
    {
        $branch = $this->currentBranch($request);
        abort_unless($letter->branch_id === $branch->id, 404);

        return $branch;
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
