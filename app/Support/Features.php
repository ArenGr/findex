<?php

namespace App\Support;

use App\Models\FeatureToggle;

/**
 * Every part of the site an admin can switch off, and what it depends on.
 *
 * The registry is the source of truth for which flags exist; feature_toggles
 * rows are only their on/off state. A key missing from the table falls back to
 * `default` here, so a flag added in code works before anyone runs
 * `features:sync`, and a key in the table that is not here is ignored.
 *
 * `parent` makes a flag inherit: switching off `travel` switches off
 * `travel_voice_fill` with it, whatever that row says.
 */
final class Features
{
    public const RATES = 'rates';

    public const RATES_MAP = 'rates_map';

    public const RATES_HISTORY = 'rates_history';

    public const RATE_ALERTS = 'rate_alerts';

    public const BANK_PRODUCTS = 'bank_products';

    public const INSURANCE = 'insurance';

    public const TRAVEL = 'travel';

    public const TRAVEL_VOICE_FILL = 'travel_voice_fill';

    public const VISA = 'visa';

    public const EXCHANGE = 'exchange';

    public const ARTICLES = 'articles';

    public const ORGANIZATIONS = 'organizations';

    public const REVIEWS = 'reviews';

    public const COMPARE = 'compare';

    public const CUSTOMER_REGISTRATION = 'customer_registration';

    public const ORGANIZATION_REGISTRATION = 'organization_registration';

    public const WRITER_REGISTRATION = 'writer_registration';

    public const GOOGLE_AUTH = 'google_auth';

    public const TELEGRAM = 'telegram';

    public const PUBLIC_API = 'public_api';

    public const WIDGETS = 'widgets';

    public const ADS = 'ads';

    public const REPORTS = 'reports';

    public const LLM_REPORTS = 'llm_reports';

    public const SCRAPE_RATES = 'scrape_rates';

    public const SCRAPE_MORTGAGES = 'scrape_mortgages';

    /**
     * The bank product pages, which were the original feature_toggles rows.
     * Their keys are category slugs - see OfferController::CATEGORIES.
     */
    private const BANK_PRODUCT_PAGES = [
        'mortgages' => 'Mortgages',
        'personal-loans' => 'Personal loans',
        'banking' => 'Savings & current accounts',
        'credit-cards' => 'Credit cards',
        'business-loans' => 'Business loans',
        'investing' => 'Investing',
        'student-loans' => 'Student loans',
    ];

    /**
     * @return array<string, array{group: string, label: string, description: string, default: bool, parent: string|null}>
     */
    public static function all(): array
    {
        static $all = null;

        if ($all !== null) {
            return $all;
        }

        $f = fn (string $group, string $label, string $description, bool $default = true, ?string $parent = null) => compact('group', 'label', 'description', 'default', 'parent');

        $all = [
            self::RATES => $f('Core', 'Exchange rates', 'The rates table, currency pages and the whole /rates section.'),
            self::RATES_MAP => $f('Core', 'Branch map', 'The map view on the rates page. Needs branch coordinates.', true, self::RATES),
            self::RATES_HISTORY => $f('Core', 'Rate history', 'Historical charts and the "See rate history" link.', true, self::RATES),
            self::RATE_ALERTS => $f('Core', 'Rate alerts', 'Customers subscribing to rate movements by email or Telegram.', true, self::RATES),

            self::BANK_PRODUCTS => $f('Verticals', 'Bank products', 'The /banks hub and its menu. Off hides every product page below.'),
        ];

        foreach (self::BANK_PRODUCT_PAGES as $slug => $label) {
            $all[$slug] = $f('Verticals', $label, "The /banks/{$slug} page and its menu entry.", true, self::BANK_PRODUCTS);
        }

        $all += [
            self::INSURANCE => $f('Verticals', 'Auto insurance', 'Insurance quote requests and the insurer directory.'),
            self::TRAVEL => $f('Verticals', 'Travel quotes', 'Travel quote requests, agency offers and everything under /tourism.'),
            self::TRAVEL_VOICE_FILL => $f('Verticals', 'Voice trip fill', 'Filling the travel form by voice. Each use makes two paid OpenAI calls.', true, self::TRAVEL),
            self::VISA => $f('Verticals', 'Visa support', 'Visa support requests and the offers visa agencies send back, under /visa.'),
            self::EXCHANGE => $f('Verticals', 'Exchange quotes', '"Get a better rate" - asking organizations to beat a published rate.'),
            self::ARTICLES => $f('Verticals', 'Articles', 'Published articles and the writer-facing side of them.'),
            self::ORGANIZATIONS => $f('Verticals', 'Organization directory', 'The /organizations listing and each organization profile.'),
            self::REVIEWS => $f('Verticals', 'Customer reviews', 'Leaving and displaying organization reviews.', true, self::ORGANIZATIONS),
            self::COMPARE => $f('Verticals', 'Compare tray', 'Side-by-side organization comparison.', true, self::ORGANIZATIONS),

            self::CUSTOMER_REGISTRATION => $f('Accounts', 'Customer sign-up', 'New customers registering. Existing accounts can still log in.'),
            self::ORGANIZATION_REGISTRATION => $f('Accounts', 'Organization sign-up', 'Banks and agencies registering themselves.'),
            self::WRITER_REGISTRATION => $f('Accounts', 'Writer sign-up', 'Writer registration. Invite-only, with no public link.'),
            self::GOOGLE_AUTH => $f('Accounts', 'Sign in with Google', 'The Google button on login and register.'),

            self::TELEGRAM => $f('Integrations', 'Telegram', 'The bot: partner notifications, alert delivery and the webhook.'),
            self::PUBLIC_API => $f('Integrations', 'Public API', 'The /api/v1 endpoints, API keys and the docs page.'),
            self::WIDGETS => $f('Integrations', 'Embeddable widgets', 'The rate widgets other sites embed.'),
            self::ADS => $f('Integrations', 'Ad slots', 'Promoted placements rendered around the site.'),
            self::REPORTS => $f('Integrations', 'Organization reports', 'Reports organizations can request about their own data.'),
            self::LLM_REPORTS => $f('Integrations', 'AI report summaries', 'The AI written summary inside a report. Paid LLM calls.', true, self::REPORTS),

            self::SCRAPE_RATES => $f('Automation', 'Rate scraping', 'The daily scrape that keeps published rates current.'),
            self::SCRAPE_MORTGAGES => $f('Automation', 'Mortgage scraping', 'The daily mortgage offer scrape.'),
        ];

        return $all;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /** Whether the feature and every feature it depends on are switched on. */
    public static function enabled(string $key): bool
    {
        return self::states()[$key] ?? false;
    }

    public static function disabled(string $key): bool
    {
        return ! self::enabled($key);
    }

    /**
     * Every flag resolved to a final yes/no, with parents applied.
     *
     * @return array<string, bool>
     */
    public static function states(): array
    {
        $stored = FeatureToggle::storedStates();
        $resolved = [];

        foreach (self::all() as $key => $meta) {
            $resolved[$key] = $stored[$key] ?? $meta['default'];
        }

        // A second pass rather than recursion: the registry is declared
        // parents-first, so one walk settles the whole tree.
        foreach (self::all() as $key => $meta) {
            if ($meta['parent'] !== null && ! ($resolved[$meta['parent']] ?? false)) {
                $resolved[$key] = false;
            }
        }

        return $resolved;
    }

    /**
     * @return array<int, string>
     */
    public static function children(string $key): array
    {
        return array_keys(array_filter(self::all(), fn (array $meta) => $meta['parent'] === $key));
    }
}
