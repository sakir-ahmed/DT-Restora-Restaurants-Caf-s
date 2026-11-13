<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Admin\PreorderController;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\Preorder;
use App\Services\PreorderService;
use App\Support\PreorderInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HtmlResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shareable public side of the menu book: anyone with the link can browse it and send a
 * pre-order. A pre-order only records what the guest wants, so a script cannot hurt the kitchen,
 * but the form is still rate-limited.
 */
class PreorderMenuController extends Controller
{
    public function show(string $branchCode): Response
    {
        return $this->book($this->branch($branchCode), 'web.preorder');
    }

    /**
     * The branch menu at /{branch}/menu. This is the address printed as a QR code in the hard copy
     * menu book, so it shows the same public menu book and takes orders the same way.
     */
    public function branchMenu(string $branchCode): Response
    {
        return $this->book($this->branch($branchCode), 'web.branch-menu');
    }

    private function book(Branch $branch, string $shareRoute): Response
    {
        $categories = MenuCategory::query()
            ->where('branch_id', $branch->id)
            ->active()
            ->ordered()
            ->with(['items' => fn ($query) => $query->available()->with(['variants', 'addOns', 'media'])->orderBy('sort_order')])
            ->get()
            ->filter(fn (MenuCategory $category): bool => $category->items->isNotEmpty())
            ->values();

        return Inertia::render('web/PreorderBook', [
            'branch' => $branch->only(['id', 'name', 'name_bn', 'code', 'address', 'address_bn', 'phone', 'vat_percent', 'vat_inclusive', 'service_charge_percent']),
            'categories' => $categories->toArray(),
            'shareUrl' => route($shareRoute, ['branchCode' => strtolower($branch->code)]),
            'placed' => session()->pull('preorder_placed'),
        ]);
    }

    public function store(Request $request, string $branchCode, PreorderService $service): RedirectResponse
    {
        $branch = $this->branch($branchCode);

        $guestKey = 'preorder:'.$branch->id.':'.sha1((string) $request->ip());

        if (RateLimiter::tooManyAttempts($guestKey, 5) || RateLimiter::tooManyAttempts('preorder:'.$branch->id, 100)) {
            throw ValidationException::withMessages(['items' => __('preorders.too_many')]);
        }

        $validated = $request->validate(PreorderInput::rules($branch->id, withTable: false), [
            'items.required' => __('preorders.cart_empty'),
            'items.min' => __('preorders.cart_empty'),
        ]);

        RateLimiter::hit($guestKey, 3600);
        RateLimiter::hit('preorder:'.$branch->id, 3600);

        $preorder = $service->place($branch, $validated['items'], PreorderInput::details($validated), Preorder::SOURCE_PUBLIC);

        return back()->with('preorder_placed', PreorderController::placed($preorder));
    }

    /**
     * The guest copy to print, opened from the link the guest was given.
     */
    public function slip(Request $request, string $token): HtmlResponse
    {
        $preorder = Preorder::query()->withoutBranchScope()->where('token', $token)->firstOrFail();

        return PreorderController::slip($preorder, autoPrint: $request->boolean('auto'))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function branch(string $branchCode): Branch
    {
        return Branch::query()->where('is_active', true)->where('code', strtoupper($branchCode))->firstOrFail();
    }
}
