<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

// Configurações da conta servem os dois ambientes: auth multi-guard
// (web do portal, gestao do console) — o primeiro guard autenticado
// resolve $request->user().
Route::middleware(['auth:web,gestao', 'verified', 'lgpd.accepted'])
    ->prefix('settings')
    ->name('settings.')
    ->group(function () {
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::get('password', [PasswordController::class, 'show'])->name('password.show');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    });
