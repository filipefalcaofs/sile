<?php

use App\Http\Controllers\Regin\ReginRecebeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Raiz aponta para o portal público do cidadão.
Route::redirect('/', '/portal');

// Contrato JUCEB/REGIN (Guia Técnico v2.10): GET=3, POST=3 recebido / 5 duplicado.
// Público de propósito — a JUCEB chama a URL municipal, sem sessão Laravel.
Route::middleware('throttle:regin-recebe')->group(function (): void {
    Route::get('/api_integracao/recebe', [ReginRecebeController::class, 'ping'])->name('regin.recebe.ping');
    Route::post('/api_integracao/recebe', [ReginRecebeController::class, 'store'])->name('regin.recebe.store');
});

// Landing pública do portal (rota nomeada "home" — usada pelos redirects de guest do framework).
Route::get('/portal', fn () => Inertia::render('home'))->name('home');

require __DIR__.'/portal.php';
require __DIR__.'/gestao.php';
