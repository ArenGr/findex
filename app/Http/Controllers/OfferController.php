<?php

namespace App\Http\Controllers;

use App\Models\FeatureToggle;
use App\Models\MortgageOffer;
use App\Services\MortgageMarket;
use Illuminate\View\View;

class OfferController extends Controller
{
    /**
     * Every bank product page the app can render, in the order they appear
     * in the menu and on the hub. Whether any given one is actually visible
     * is a runtime decision - an admin flips it in Feature Toggles (see
     * FeatureToggle), and a disabled category is hidden from the nav and
     * the hub and 404s its own page.
     *
     * A slug here without a matching feature_toggles row simply stays off,
     * so adding one is safe: seed the row when the page is ready to show.
     */
    public const CATEGORIES = [
        'mortgages',
        'personal-loans',
        'banking',
        'credit-cards',
        'business-loans',
        'investing',
        'student-loans',
    ];

    /**
     * Categories with a bespoke page. Everything else renders the shared
     * sample layout (banks.sample) - a real comparison table filled with
     * clearly-marked example figures plus the list of fields we need from
     * the bank, so a page exists to show a partner before any data lands.
     */
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

    /**
     * $locale is unused directly (App::getLocale() covers that) but must
     * stay as the first parameter - route parameters bind to controller
     * method parameters by position, not name, and this route sits under
     * the {locale}-prefixed group, so $category would otherwise receive
     * the locale segment's value instead of its own. Same convention as
     * ArticleController::show()/OrganizationController::show().
     */
    public function show(string $locale, string $category, MortgageMarket $market): View
    {
        // The route's regex constraint (see pages.php) already rejects
        // anything not in CATEGORIES; this is the switched-off case, which
        // is a runtime value the route can't express.
        abort_unless(in_array($category, self::enabledCategories(), true), 404);

        $data = ['category' => $category];

        // The mortgages page follows the two-tier pattern: a market benchmark
        // and a product-by-product overview for context (computed here),
        // above the interactive offers table that ranks client-side.
        if ($category === 'mortgages') {
            $data += $this->mortgageData($market);
        }

        return view(self::CUSTOM_VIEWS[$category] ?? 'banks.sample', $data);
    }

    /**
     * The mortgage page's market-context tier: a headline benchmark for the
     * default AMD secondary cohort, and a product-by-product overview across
     * every collected cohort. The per-bank offers table itself ranks
     * client-side in the calculator (x-mortgage-offers-table), so it can
     * re-rank live as the visitor changes their scenario.
     *
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
