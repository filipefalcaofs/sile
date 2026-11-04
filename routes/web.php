<?php

use App\Http\Controllers\Integracao\BiController;
use App\Http\Controllers\Integracao\BiProcessosController;
use App\Http\Controllers\Integracao\ConsultaTvlController;
use App\Http\Controllers\Regin\ReginDocumentacaoController;
use App\Http\Controllers\Regin\ReginRecebeController;
use App\Http\Controllers\VerificacaoDocumentoController;
use App\Http\Middleware\AutenticaBi;
use App\Http\Middleware\AutenticaRecebeRegin;
use App\Http\Middleware\LogInboundIntegration;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Raiz aponta para o portal público do cidadão.
Route::redirect('/', '/portal');

// Verificação PÚBLICA de autenticidade de documentos (Viabilidade, indeferimento,
// comprovante de protocolo) pelo código de verificação — sem login, com o
// throttle público da consulta de protocolo. Payload mínimo (LGPD).
Route::get('/verificar-documento/{codigo}', [VerificacaoDocumentoController::class, 'show'])
    ->middleware('throttle:consulta-protocolo')
    ->name('verificar-documento');

// Swagger UI da URL municipal. Fora do throttle do recebe para a tela não
// consumir a cota das chamadas da JUCEB.
Route::get('/api_integracao/docs', [ReginDocumentacaoController::class, 'show'])->name('regin.docs');
Route::get('/api_integracao/docs/openapi.yaml', [ReginDocumentacaoController::class, 'spec'])->name('regin.docs.spec');

// Especificação da consulta de Viabilidade (SEFAZ/SIGS), exibida no Swagger UI único
// acima. Declaradas antes do {numero}.
Route::get('/api_integracao/tvl/docs', [ConsultaTvlController::class, 'documentacao'])->name('integracao.tvl.docs');
Route::get('/api_integracao/tvl/docs/openapi.yaml', [ConsultaTvlController::class, 'especificacao'])->name('integracao.tvl.docs.spec');

// Especificação da API de indicadores do BI, no mesmo Swagger UI.
Route::get('/api_integracao/bi/docs/openapi.yaml', [BiController::class, 'especificacao'])->name('integracao.bi.docs.spec');

// Contrato JUCEB/REGIN (Guia Técnico v2.10): GET=3, POST=3 recebido / 5 duplicado.
// Público de propósito — a JUCEB chama a URL municipal, sem sessão Laravel.
Route::middleware(LogInboundIntegration::class)->group(function (): void {
    Route::middleware('throttle:regin-recebe')->group(function (): void {
        Route::get('/api_integracao/recebe', [ReginRecebeController::class, 'ping'])->name('regin.recebe.ping');
        Route::post('/api_integracao/recebe', [ReginRecebeController::class, 'store'])
            ->middleware(AutenticaRecebeRegin::class)
            ->name('regin.recebe.store');
    });

    // Consulta de Viabilidade pela SEFAZ/SIGS (alvará e DAM): login com usuário e
    // senha devolve um token Bearer com validade.
    Route::post('/api_integracao/tvl/autenticar', [ConsultaTvlController::class, 'autenticar'])
        ->middleware('throttle:regin-recebe')
        ->name('integracao.tvl.autenticar');
    Route::get('/api_integracao/tvl/{numero}', [ConsultaTvlController::class, 'show'])
        ->where('numero', '[A-Za-z0-9\\-]+')
        ->middleware('throttle:regin-recebe')
        ->name('integracao.tvl.show');

    // Indicadores agregados para o BI da SEDUR: login com usuário e senha devolve um
    // token Bearer; o catálogo lista os indicadores e cada um responde por slug.
    Route::post('/api_integracao/bi/autenticar', [BiController::class, 'autenticar'])
        ->middleware('throttle:bi-api')
        ->name('integracao.bi.autenticar');
    Route::middleware(['throttle:bi-api', AutenticaBi::class])
        ->prefix('api_integracao/bi/indicadores')
        ->group(function (): void {
            Route::get('/', [BiController::class, 'catalogo'])->name('integracao.bi.catalogo');
            Route::get('/{slug}', [BiController::class, 'indicador'])
                ->where('slug', '[a-z0-9\\-]+')
                ->name('integracao.bi.indicador');
        });
    Route::middleware(['throttle:bi-api', AutenticaBi::class])->group(function (): void {
        Route::get('/api_integracao/bi/processos', [BiProcessosController::class, 'index'])->name('integracao.bi.processos');
        Route::get('/api_integracao/bi/processos/{processo}', [BiProcessosController::class, 'show'])
            ->where('processo', '[A-Za-z0-9\\-]+')
            ->name('integracao.bi.processos.show');
    });
});

// Landing pública do portal (rota nomeada "home" — usada pelos redirects de guest do framework).
Route::get('/portal', fn () => Inertia::render('home'))->name('home');

require __DIR__.'/portal.php';
require __DIR__.'/gestao.php';
