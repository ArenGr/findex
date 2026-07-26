<?php

use App\Http\Controllers\Organization\ProfileController as OrganizationProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/profile', [OrganizationProfileController::class, 'edit'])->name('profile.edit');
Route::put('/profile', [OrganizationProfileController::class, 'update'])->name('profile.update');
