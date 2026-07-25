<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Bare placeholder - the real writer dashboard (article drafting/publishing)
 * doesn't exist yet, this just shows a pending-approval or welcome message
 * so a newly-registered writer has somewhere to land.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        return view('writer.dashboard.index', [
            'writer' => Auth::guard('writer')->user()->writer,
        ]);
    }
}
