<?php

namespace App\Http\Controllers;

use App\Models\FeatureToggle;
use App\Models\MortgageOffer;
use App\Services\MortgageMarket;
use Illuminate\View\View;

class OfferController extends Controller
{
    // Every bank product page the app can render, in the order they appear in the menu and on the hub.
    public const CATEGORIES = [
        'mortgages',
        'personal-loans',
        'banking',
        'credit-cards',
        'business-loans',
        'investing',
        'student-loans',
    ];

    // Categories with a bespoke page.
    private const CUSTOM_VIEWS = [
        'mortgages' => 'banks.mortgages',
        'personal-loans' => 'banks.personal-loans',
        'banking' => 'banks.banking',
    ];

    /**
     * Known categories that are currently switched on, in CATEGORIES order.
     *
     * @return array<int, string>
     */
    public static function enabledCategories(): array
    {
        return array_values(array_intersect(self::CATEGORIES, FeatureToggle::enabledKeys()));
    }

    public function index(): View
    {
        return view('banks.index', ['categories' => self::enabledCategories()]);
    }

    public function show(string $locale, string $category, MortgageMarket $market): View
    {
        abort_unless(in_array($category, self::enabledCategories(), true), 404);

        $data = ['category' => $category];

        if ($category === 'mortgages') {
            $data += $this->mortgageData($market);
        }

        return view(self::CUSTOM_VIEWS[$category] ?? 'banks.sample', $data);
    }

    /**
     * @return array{
     *     mortgageBenchmark: array<string, mixed>,
     *     mortgageOverview: list<array<string, mixed>>,
     * }
     */
    private function mortgageData(MortgageMarket $market): array
    {
        $offers = MortgageOffer::query()
            ->whereHas('organization', fn ($query) => $query->active())
            ->with('organization')
            ->get();

        $secondaryAmd = $offers
            ->where('category', 'secondary_market')
            ->where('currency', 'AMD');

        return [
            'mortgageBenchmark' => $market->benchmark($secondaryAmd),
            'mortgageOverview' => $market->overview($offers),
        ];
    }
}
