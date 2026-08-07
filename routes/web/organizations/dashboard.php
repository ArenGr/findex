<?php

use App\Enums\UserRole;
use App\Http\Controllers\Organization\BranchController;
use App\Http\Controllers\Organization\DashboardController as OrganizationDashboardController;
use App\Http\Controllers\Organization\ReportRequestController;
use App\Http\Controllers\Organization\ReviewReplyController;
use App\Http\Controllers\Organization\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('org')->name('org.')->middleware(['auth:organization', 'banned'])->group(function () {
    // The role:organization middleware is scoped to this dashboard group
    // only - see auth/auth.php's /org logout route for why it's kept off
    // there (a wrong-role session, though it shouldn't happen, should
    // still be able to log itself out).
    Route::middleware('role:organization,'.UserRole::ORGANIZATION->value)->prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/', [OrganizationDashboardController::class, 'index'])->name('index');

        require __DIR__.'/settings.php';

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

        // Everything below is gated to the organization types it applies to -
        // see rates.php, tourism.php and insurance.php.
        require __DIR__.'/rates.php';
        require __DIR__.'/tourism.php';
        require __DIR__.'/insurance.php';
    });
});
