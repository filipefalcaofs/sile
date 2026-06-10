<?php

use App\Http\Controllers\Settings\PasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'lgpd.accepted'])
    ->prefix('settings')
    ->name('settings.')
    ->group(function () {
        Route::get('password', [PasswordController::class, 'show'])->name('password.show');
    });
