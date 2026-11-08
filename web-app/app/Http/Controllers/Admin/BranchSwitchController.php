<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $branch = Branch::query()->findOrFail((int) $request->input('branch_id'));

        $user = $request->user('staff');

        abort_unless($user->canAccessBranch($branch->id), 404);

        session(['branch_id' => $branch->id]);

        return back();
    }
}
