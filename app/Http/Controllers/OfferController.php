<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class OfferController extends Controller
{
    /**
     * Each was previously a tab on one shared /offers page - split into its
     * own URL so a category is bookmarkable/shareable/deep-linkable, and so
     * a visitor landing on an empty "coming soon" tab (the old page's
     * default) isn't the first thing they see. 'available' means a real
     * view exists in show() below; everything else falls back to the
     * generic coming-soon page. Public - routes/web/public/pages.php reads
     * the keys to build the {category} route's whitelist regex, so a
     * request for an unknown slug (or a literal collision like "all", the
     * bank directory's own sibling route) never reaches this controller at
     * all rather than being turned away here.
     */
    public const CATEGORIES = [
        'mortgages' => true,
        'personal-loans' => true,
        'banking' => true,
        'credit-cards' => false,
        'business-loans' => false,
        'investing' => false,
        'student-loans' => false,
    ];

    public function index(): View
    {
        return view('banks.index', ['categories' => self::CATEGORIES]);
    }

    /**
     * $locale is unused directly (App::getLocale() covers that) but must
     * stay as the first parameter - route parameters bind to controller
     * method parameters by position, not name, and this route sits under
     * the {locale}-prefixed group, so $category would otherwise receive
     * the locale segment's value instead of its own. Same convention as
     * ArticleController::show()/OrganizationController::show().
     */
    public function show(string $locale, string $category): View
    {
        // Backstop only - the route's own regex constraint (see pages.php)
        // already keeps anything not in CATEGORIES from reaching here.
        abort_unless(array_key_exists($category, self::CATEGORIES), 404);

        return match ($category) {
            'mortgages' => view('banks.mortgages'),
            'personal-loans' => view('banks.personal-loans'),
            'banking' => view('banks.banking'),
            default => view('banks.coming-soon', ['category' => $category]),
        };
    }
}
