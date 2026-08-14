<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ApiDocsController extends Controller
{
    public function index(): View
    {
        return view('api.docs', [
            // Straight from config/api.php, so the page cannot disagree with
            // what the limiter actually enforces.
            'plans' => config('api.plans'),
            'anonymous' => config('api.anonymous'),
            'endpoints' => [
                ['path' => '/api/v1/currencies', 'summary' => 'Every currency Findex tracks.'],
                ['path' => '/api/v1/rates?currency=USD', 'summary' => 'Current rates from every bank and exchange office.'],
                ['path' => '/api/v1/rates/best?currency=USD', 'summary' => 'The highest buy and lowest sell rate in the country.'],
                ['path' => '/api/v1/rates/average?currency=USD', 'summary' => 'The mean across everyone quoting.'],
                ['path' => '/api/v1/rates/history?currency=USD&days=7', 'summary' => 'Daily best and average rates over time.'],
                ['path' => '/api/v1/organizations', 'summary' => 'Banks and exchange offices publishing rates.'],
                ['path' => '/api/v1/organizations/{slug}/rates', 'summary' => 'Everything one organization quotes.'],
            ],
        ]);
    }
}
