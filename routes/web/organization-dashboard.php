<?php

use App\Enums\UserRole;
use App\Http\Controllers\Organization\BranchController;
use App\Http\Controllers\Organization\CurrencyRateController;
use App\Http\Controllers\Organization\DashboardController as OrganizationDashboardController;
use App\Http\Controllers\Organization\InsuranceController as OrganizationInsuranceController;
use App\Http\Controllers\Organization\ProfileController as OrganizationProfileController;
use App\Http\Controllers\Organization\QuoteTemplateController;
use App\Http\Controllers\Organization\ReportRequestController;
use App\Http\Controllers\Organization\ReviewReplyController;
use App\Http\Controllers\Organization\TeamController;
use App\Http\Controllers\Organization\TourismController as OrganizationTourismController;
use App\Models\Organization;
use Illuminate\Support\Facades\Route;

// role:organization,<value> kept off /logout (see organization-auth.php) on
// purpose - a wrong-role session on this guard (shouldn't happen, see
// EnsureUserRole's docblock) should still be able to log itself out rather
// than getting stuck 403'd.
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

    Route::middleware('org.type:'.implode(',', Organization::INSURANCE_TYPES))->group(function () {
        Route::get('/insurance', [OrganizationInsuranceController::class, 'index'])->name('insurance.index');
    });
});
