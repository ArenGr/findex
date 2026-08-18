<?php

use App\Http\Controllers\Organization\QuoteTemplateController;
use App\Http\Controllers\Organization\TourismController as OrganizationTourismController;
use App\Http\Controllers\Organization\TravelRequestController;
use App\Models\Organization;
use Illuminate\Support\Facades\Route;

// Tourism lead management and quote templates only apply to tourism
// organizations (see Organization::TOURISM_TYPES).
Route::middleware('org.type:'.implode(',', Organization::TOURISM_TYPES))->group(function () {
    // The agency's inbox of travel requests it has been sent. Every route
    // here is scoped to the signed-in agency's own organization inside the
    // controller - see TravelRequestController::ownedResponse().
    Route::get('/travel-requests', [TravelRequestController::class, 'index'])->name('travel-requests.index');
    Route::get('/travel-requests/{response}', [TravelRequestController::class, 'show'])->name('travel-requests.show');
    Route::post('/travel-requests/{response}/offer', [TravelRequestController::class, 'store'])->name('travel-requests.offer.store');
    Route::post('/travel-requests/{response}/decline', [TravelRequestController::class, 'decline'])->name('travel-requests.decline');

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
