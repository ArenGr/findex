<?php

use App\Http\Controllers\Organization\InsuranceController as OrganizationInsuranceController;
use App\Models\Organization;
use Illuminate\Support\Facades\Route;

// Insurance quote dashboard only applies to insurance organizations
// (see Organization::INSURANCE_TYPES).
Route::middleware('org.type:'.implode(',', Organization::INSURANCE_TYPES))->group(function () {
    Route::get('/insurance', [OrganizationInsuranceController::class, 'index'])->name('insurance.index');
});
