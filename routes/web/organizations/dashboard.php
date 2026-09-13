<?php

use App\Enums\UserRole;
use App\Http\Controllers\Organization\BranchController;
use App\Http\Controllers\Organization\DashboardController as OrganizationDashboardController;
use App\Http\Controllers\Organization\ReportRequestController;
use App\Http\Controllers\Organization\ReviewReplyController;
use App\Http\Controllers\Organization\TeamController;
use App\Support\Features;
use Illuminate\Support\Facades\Route;

Route::prefix('org')->name('org.')->middleware(['auth:organization', 'banned'])->group(function () {
    Route::middleware('role:organization,'.UserRole::ORGANIZATION->value)->prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/', [OrganizationDashboardController::class, 'index'])->name('index');

        require __DIR__.'/settings.php';

        Route::middleware('feature:'.Features::REVIEWS)->group(function () {
            Route::get('/reviews', [ReviewReplyController::class, 'index'])->name('reviews.index');
            Route::post('/reviews/{review}/reply', [ReviewReplyController::class, 'store'])->name('reviews.reply');
        });

        Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

        Route::middleware('feature:'.Features::REPORTS)->group(function () {
            Route::get('/reports', [ReportRequestController::class, 'index'])->name('reports.index');
            Route::get('/reports/create', [ReportRequestController::class, 'create'])->name('reports.create');
            Route::post('/reports', [ReportRequestController::class, 'store'])->name('reports.store');
            Route::get('/reports/{reportRequest}', [ReportRequestController::class, 'show'])->name('reports.show');
        });

        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');

        Route::middleware('feature:'.Features::RATES)->group(__DIR__.'/rates.php');
        Route::middleware('feature:'.Features::TRAVEL)->group(__DIR__.'/tourism.php');
        Route::middleware('feature:'.Features::INSURANCE)->group(__DIR__.'/insurance.php');
    });
});
