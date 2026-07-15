<?php

use App\Enums\UserRole;
use App\Http\Controllers\AutoInsuranceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\DestinationAlertController;
use App\Http\Controllers\Organization\Auth\AuthenticatedSessionController as OrganizationAuthenticatedSessionController;
use App\Http\Controllers\Organization\Auth\RegisteredOrganizationController;
use App\Http\Controllers\Organization\BranchController;
use App\Http\Controllers\Organization\CurrencyRateController;
use App\Http\Controllers\Organization\DashboardController as OrganizationDashboardController;
use App\Http\Controllers\Organization\ProfileController as OrganizationProfileController;
use App\Http\Controllers\Organization\ReportRequestController;
use App\Http\Controllers\Organization\QuoteTemplateController;
use App\Http\Controllers\Organization\ReviewReplyController;
use App\Http\Controllers\Organization\TeamController;
use App\Http\Controllers\Organization\TourismController as OrganizationTourismController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PartnerResponseController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\RateAlertController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TelegramWebhookController;
use App\Models\Organization;
use Illuminate\Support\Facades\Route;

// Redirect the bare domain to the visitor's preferred supported language,
// falling back to the site default. Every real page lives under /{locale}/
// so each language gets its own indexable, shareable URL (see SetLocale).
Route::get('/', function () {
    $available = array_keys(config('localization.available'));
    $preferred = request()->getPreferredLanguage($available);

    return redirect('/' . ($preferred ?? config('localization.default')));
});

Route::prefix('{locale}')
    ->whereIn('locale', array_keys(config('localization.available')))
    ->middleware('setlocale')
    ->group(function () {
        Route::get('/', function () {
            return view('home');
        })->name('home');

        // Internal design reference only - never routable in production so it
        // can't be stumbled onto as an unlabeled "real" page.
        if (! app()->isProduction()) {
            Route::get('/style-guide', function () {
                return view('style-guide');
            })->name('style-guide');
        }

        Route::get('/about', function () {
            return view('about');
        })->name('about');

        Route::get('/team', function () {
            return view('team');
        })->name('team');

        Route::get('/careers', function () {
            return view('careers');
        })->name('careers');

        Route::get('/news', function () {
            return view('company-news');
        })->name('company.news');

        Route::get('/help', function () {
            return view('help');
        })->name('help');

        Route::get('/faq', function () {
            return view('faq');
        })->name('faq');

        Route::get('/contact', function () {
            return view('contact');
        })->name('contact');

        Route::get('/terms', function () {
            return view('legal.terms');
        })->name('terms');

        Route::get('/privacy', function () {
            return view('legal.privacy');
        })->name('privacy');

        Route::get('/cookies', function () {
            return view('legal.cookies');
        })->name('cookies');

        Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/compare', [CompareController::class, 'show'])->name('organizations.compare');
        Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
        Route::get('/rates', [RateController::class, 'index'])->name('rates.index');

        Route::get('/register', function () {
            return view('auth.register-choice');
        })->name('register');

        // Guard-agnostic (see VerifyEmailController) - reachable whether the
        // link was emailed to a customer or an organization account.
        Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::middleware('guest')->group(function () {
            Route::get('/register/customer', [RegisteredUserController::class, 'create'])->name('register.customer');
            Route::post('/register/customer', [RegisteredUserController::class, 'store']);

            Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
        });

        // Open to guests (see ReviewController::store) - 'banned' still
        // blocks a signed-in banned user, it's simply a no-op for guests.
        Route::middleware(['banned', 'throttle:reviews'])->group(function () {
            Route::post('/organizations/{organization}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
        });

        Route::get('/tourism', [QuoteRequestController::class, 'create'])->name('tourism.request');

        // Registered before the {quoteRequest} wildcard below so "mine" and
        // "resend" aren't swallowed by it and treated as a request ID.
        Route::get('/tourism/mine', [QuoteRequestController::class, 'mine'])
            ->middleware(['auth', 'banned'])
            ->name('tourism.mine');

        Route::get('/tourism/resend', [QuoteRequestController::class, 'resendForm'])->name('tourism.resend');

        // Same response either way (see QuoteRequestController::resend) so
        // this can't be used to check which emails have filed a request.
        Route::middleware(['banned', 'throttle:quote_link_resend'])->group(function () {
            Route::post('/tourism/resend', [QuoteRequestController::class, 'resend'])->name('tourism.resend.send');
        });

        // The secure, no-login page a partner lands on from the Telegram
        // notification - registered before the {quoteRequest} wildcard below
        // for the same reason as "mine" and "resend" above.
        Route::get('/tourism/respond/{token}', [PartnerResponseController::class, 'show'])->name('tourism.respond');

        Route::middleware('throttle:quote_response_submit')->group(function () {
            Route::post('/tourism/respond/{token}', [PartnerResponseController::class, 'store'])->name('tourism.respond.store');
        });

        Route::get('/tourism/{quoteRequest}', [QuoteRequestController::class, 'show'])->name('tourism.show');

        Route::middleware(['auth', 'banned'])->group(function () {
            Route::post('/tourism/{quoteRequest}/suggestions/{suggestion}/claim', [QuoteRequestController::class, 'claimSuggestion'])
                ->name('tourism.suggestions.claim');
        });

        // Open to guests, same abuse guard as reviews above - each submission
        // fans out to every matching partner, so this also protects partners.
        Route::middleware(['banned', 'throttle:quote_requests'])->group(function () {
            Route::post('/tourism', [QuoteRequestController::class, 'store'])->name('tourism.request.store');
        });

        Route::middleware(['banned', 'throttle:quote_requests'])->group(function () {
            Route::post('/tourism/destination-alerts', [DestinationAlertController::class, 'store'])->name('tourism.destination-alerts.store');
        });

        Route::get('/insurance/auto', [AutoInsuranceController::class, 'create'])->name('insurance.auto.request');

        Route::get('/insurance/auto/{autoInsuranceRequest}', [AutoInsuranceController::class, 'show'])->name('insurance.auto.show');

        // Same abuse guard as the tourism request above - each submission
        // fans out to every matching insurance partner.
        Route::middleware(['banned', 'throttle:quote_requests'])->group(function () {
            Route::post('/insurance/auto', [AutoInsuranceController::class, 'store'])->name('insurance.auto.request.store');
        });

        Route::middleware(['auth', 'banned'])->group(function () {
            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

            Route::get('/alerts', [RateAlertController::class, 'index'])->name('alerts.index');
            Route::post('/alerts', [RateAlertController::class, 'store'])->name('alerts.store');
            Route::patch('/alerts/{rateAlert}/toggle', [RateAlertController::class, 'toggle'])->name('alerts.toggle');
            Route::delete('/alerts/{rateAlert}', [RateAlertController::class, 'destroy'])->name('alerts.destroy');

            Route::post('/email/verification-notification', [VerifyEmailController::class, 'resendForCustomer'])
                ->middleware('throttle:6,1')
                ->name('verification.send');
        });

        Route::prefix('org')->name('org.')->group(function () {
            Route::middleware('guest:organization')->group(function () {
                Route::get('/login', [OrganizationAuthenticatedSessionController::class, 'create'])->name('login');
                Route::post('/login', [OrganizationAuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

                Route::get('/register', [RegisteredOrganizationController::class, 'create'])->name('register');
                Route::post('/register', [RegisteredOrganizationController::class, 'store']);
            });

            Route::middleware('auth:organization')->group(function () {
                Route::post('/logout', [OrganizationAuthenticatedSessionController::class, 'destroy'])->name('logout');

                Route::post('/email/verification-notification', [VerifyEmailController::class, 'resendForOrganization'])
                    ->middleware('throttle:6,1')
                    ->name('verification.send');

                // role:organization,<value> kept off /logout above on
                // purpose - a wrong-role session on this guard (shouldn't
                // happen, see EnsureUserRole's docblock) should still be
                // able to log itself out rather than getting stuck 403'd.
                Route::middleware('role:organization,'.UserRole::ORGANIZATION->value)->prefix('dashboard')->name('dashboard.')->group(function () {
                    Route::get('/', [OrganizationDashboardController::class, 'index'])->name('index');

                    Route::get('/profile', [OrganizationProfileController::class, 'edit'])->name('profile.edit');
                    Route::put('/profile', [OrganizationProfileController::class, 'update'])->name('profile.update');

                    Route::get('/reviews', [ReviewReplyController::class, 'index'])->name('reviews.index');
                    Route::post('/reviews/{review}/reply', [ReviewReplyController::class, 'store'])->name('reviews.reply');

                    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
                    Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
                    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
                    Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
                    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
                    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

                    Route::get('/reports', [ReportRequestController::class, 'index'])->name('reports.index');
                    Route::get('/reports/create', [ReportRequestController::class, 'create'])->name('reports.create');
                    Route::post('/reports', [ReportRequestController::class, 'store'])->name('reports.store');
                    Route::get('/reports/{reportRequest}', [ReportRequestController::class, 'show'])->name('reports.show');

                    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
                    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
                    Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');

                    Route::middleware('org.type:'.implode(',', Organization::RATES_TYPES))->group(function () {
                        Route::get('/rates', [CurrencyRateController::class, 'index'])->name('rates.index');
                        Route::get('/rates/create', [CurrencyRateController::class, 'create'])->name('rates.create');
                        Route::post('/rates', [CurrencyRateController::class, 'store'])->name('rates.store');
                        Route::get('/rates/{rate}/edit', [CurrencyRateController::class, 'edit'])->name('rates.edit');
                        Route::put('/rates/{rate}', [CurrencyRateController::class, 'update'])->name('rates.update');
                    });

                    Route::middleware('org.type:'.implode(',', Organization::TOURISM_TYPES))->group(function () {
                        Route::get('/tourism', [OrganizationTourismController::class, 'index'])->name('tourism.index');
                        Route::post('/tourism/refresh-connect-link', [OrganizationTourismController::class, 'refreshConnectLink'])->name('tourism.refresh-connect-link');
                        Route::put('/tourism/destinations', [OrganizationTourismController::class, 'updateDestinations'])->name('tourism.destinations.update');
                        Route::put('/tourism/destinations/{destination}/pause', [OrganizationTourismController::class, 'updateDestinationPause'])->name('tourism.destinations.pause');
                        Route::put('/tourism/lead-preferences', [OrganizationTourismController::class, 'updateLeadPreferences'])->name('tourism.lead-preferences.update');

                        Route::get('/quote-templates', [QuoteTemplateController::class, 'index'])->name('quote-templates.index');
                        Route::get('/quote-templates/create', [QuoteTemplateController::class, 'create'])->name('quote-templates.create');
                        Route::post('/quote-templates', [QuoteTemplateController::class, 'store'])->name('quote-templates.store');
                        Route::get('/quote-templates/{quoteTemplate}/edit', [QuoteTemplateController::class, 'edit'])->name('quote-templates.edit');
                        Route::put('/quote-templates/{quoteTemplate}', [QuoteTemplateController::class, 'update'])->name('quote-templates.update');
                        Route::delete('/quote-templates/{quoteTemplate}', [QuoteTemplateController::class, 'destroy'])->name('quote-templates.destroy');
                    });
                });
            });
        });
    });

// A single fixed callback URL is far simpler to register with Google than a
// locale-prefixed one, so this pair lives outside the {locale} group - the
// current locale is restored via session (see GoogleAuthController).
Route::middleware('guest')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

// Telegram POSTs here directly (see TelegramWebhookController and the
// telegram:webhook command) - no locale prefix, no CSRF token, since this
// isn't a browser request. Auth is the X-Telegram-Bot-Api-Secret-Token
// header, checked in the controller.
Route::post('/telegram/webhook', TelegramWebhookController::class)->name('telegram.webhook');
