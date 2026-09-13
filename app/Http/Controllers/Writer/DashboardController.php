<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('writer.dashboard.index', [
            'writer' => Auth::guard('writer')->user()->writer,
        ]);
    }
}
