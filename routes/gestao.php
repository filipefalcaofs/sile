<?php

use App\Http\Controllers\Gestao\AccessHistoryController;
use App\Http\Controllers\Gestao\CnaeController;
use App\Http\Controllers\Gestao\DashboardController;
use App\Http\Controllers\Gestao\EmailLogController;
use App\Http\Controllers\Gestao\GeocodeController;
use App\Http\Controllers\Gestao\LoginController;
use App\Http\Controllers\Gestao\ParameterController;
use App\Http\Controllers\Gestao\RoleController;
use App\Http\Controllers\Gestao\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Login interno da retaguarda (não divulgado no portal público), em guard
// próprio (gestao): a sessão é independente da sessão do portal do cidadão.
Route::middleware('gestao.guest')->prefix('gestao')->name('gestao.')->group(function () {
    Route::get('login', fn () => Inertia::render('auth/gestao-login'))->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth:gestao')
    ->post('gestao/logout', [LoginController::class, 'destroy'])
    ->name('gestao.logout');

// Sem middleware verified: e-mail verificado é pré-condição do PRÓPRIO login
// interno (LoginController) — o aviso/reenvio de verificação pertence ao
// fluxo do portal e exige o guard web.
Route::middleware(['auth:gestao', 'permission:acessar-gestao', 'lgpd.accepted'])
    ->prefix('gestao')
    ->name('gestao.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('acessos/{user}', AccessHistoryController::class)
            ->middleware('permission:consultar-acessos-de-qualquer-conta')
            ->name('acessos.show');

        // Consulta granular separada da manutenção (HU-011 CA-04)
        Route::middleware('permission:consultar-cnaes')->group(function () {
            Route::get('cnaes', [CnaeController::class, 'index'])->name('cnaes.index');
        });

        Route::middleware('permission:manter-cnaes')->group(function () {
            Route::post('cnaes', [CnaeController::class, 'store'])->name('cnaes.store');
            Route::put('cnaes/{cnae}', [CnaeController::class, 'update'])->name('cnaes.update');
            Route::delete('cnaes/{cnae}', [CnaeController::class, 'destroy'])->name('cnaes.destroy');
        });

        Route::middleware('permission:manter-usuarios')->group(function () {
            Route::get('usuarios', [UserManagementController::class, 'index'])->name('usuarios.index');
            Route::put('usuarios/{user}/papel', [UserManagementController::class, 'updateRole'])->name('usuarios.papel.update');
            Route::put('usuarios/{user}/inativacao', [UserManagementController::class, 'toggleActivation'])->name('usuarios.inativacao.update');
        });

        Route::middleware('permission:manter-perfis')->group(function () {
            Route::get('perfis', [RoleController::class, 'index'])->name('perfis.index');
            Route::post('perfis', [RoleController::class, 'store'])->name('perfis.store');
            Route::put('perfis/{role}', [RoleController::class, 'update'])->name('perfis.update');
            Route::delete('perfis/{role}', [RoleController::class, 'destroy'])->name('perfis.destroy');
        });

        Route::middleware('permission:monitorar-emails')->group(function () {
            Route::get('emails', [EmailLogController::class, 'index'])->name('emails.index');
        });

        Route::middleware('permission:manter-parametros')->group(function () {
            Route::get('parametros', [ParameterController::class, 'index'])->name('parametros.index');
            Route::get('parametros/{parameter:key}/historico', [ParameterController::class, 'history'])->name('parametros.historico');
            Route::put('parametros/{parameter:key}', [ParameterController::class, 'update'])->name('parametros.update');
        });

        // Território (HU-029+): geocodificação atrás de permissão própria e
        // throttle parametrizado. O gate é este middleware permission: (a
        // permissão vive no guard web e resolve para o usuário do guard gestao).
        Route::middleware('permission:consultar-territorio')->prefix('territorio')->name('territorio.')->group(function () {
            Route::post('geocodificar', GeocodeController::class)->middleware('throttle:geocoding')->name('geocodificar');
        });
    });
