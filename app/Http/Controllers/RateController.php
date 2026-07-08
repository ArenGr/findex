<?php

namespace App\Http\Controllers;

use App\Models\CurrencyRate;
use Illuminate\View\View;

class RateController extends Controller
{
    public function index(): View
    {
        $rates = CurrencyRate::with(['organization', 'currency'])
            ->whereHas('organization', fn ($query) => $query->active())
            ->orderBy('organization_id')
            ->orderBy('currency_id')
            ->orderBy('rate_type')
            ->get();

        return view('rates.index', compact('rates'));
    }
}
