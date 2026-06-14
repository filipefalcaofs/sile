<?php

use App\Http\Controllers\Gestao\AccessHistoryController;
use App\Http\Controllers\Gestao\CnaeController;
use App\Http\Controllers\Gestao\ContingenciaController;
use App\Http\Controllers\Gestao\DashboardController;
use App\Http\Controllers\Gestao\DocumentRequirementController;
use App\Http\Controllers\Gestao\EmailLogController;
use App\Http\Controllers\Gestao\GeocodeController;
use App\Http\Controllers\Gestao\LoginController;
use App\Http\Controllers\Gestao\LouosController;
use App\Http\Controllers\Gestao\LouosSandboxController;
use App\Http\Controllers\Gestao\ParameterController;
use App\Http\Controllers\Gestao\RiscoCondicionanteController;
use App\Http\Controllers\Gestao\RiscoController;
use App\Http\Controllers\Gestao\RoleController;
use App\Http\Controllers\Gestao\TerritoryController;
use App\Http\Controllers\Gestao\UserManagementController;
use App\Http\Controllers\Gestao\ViabilityServiceTypeController;
use App\Http\Controllers\Portal\CnaeSearchController;
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

        // Território (HU-029+): consulta territorial, geocodificação e validação
        // de localização atrás de permissão própria. O gate é este middleware
        // permission: (a permissão vive no guard web e resolve para o usuário do
        // guard gestao). A geocodificação tem throttle parametrizado próprio.
        Route::middleware('permission:consultar-territorio')->prefix('territorio')->name('territorio.')->group(function () {
            Route::get('/', [TerritoryController::class, 'index'])->name('index');
            Route::post('geocodificar', GeocodeController::class)->middleware('throttle:geocoding')->name('geocodificar');
            Route::post('identificar', [TerritoryController::class, 'identify'])->name('identificar');
            Route::post('validar-localizacao', [TerritoryController::class, 'validateLocation'])->name('validar-localizacao');
        });

        // Classificação de risco (HU-019/HU-020/HU-052/HU-053): a consulta da
        // tabela vigente (analista/gestor/admin) é separada da manutenção
        // versionada e do CRUD de condicionantes (admin). Gate cross-guard via
        // permission: (PADRÃO 04-03). Atualizar publica NOVA versão (4-olhos),
        // nunca edição destrutiva da vigente.
        Route::middleware('permission:consultar-risco')->prefix('risco')->name('risco.')->group(function () {
            Route::get('/', [RiscoController::class, 'index'])->name('index');
            Route::get('condicionantes', [RiscoCondicionanteController::class, 'index'])->name('condicionantes.index');
        });

        Route::middleware('permission:manter-risco')->prefix('risco')->name('risco.')->group(function () {
            Route::put('publicar', [RiscoController::class, 'publish'])->name('publicar');
            Route::post('condicionantes', [RiscoCondicionanteController::class, 'store'])->name('condicionantes.store');
            Route::put('condicionantes/{condicionante}', [RiscoCondicionanteController::class, 'update'])->name('condicionantes.update');
            Route::delete('condicionantes/{condicionante}', [RiscoCondicionanteController::class, 'destroy'])->name('condicionantes.destroy');
        });

        // Quadros da LOUOS (HU-015..018/HU-046): a consulta da versão vigente dos
        // 4 Quadros (analista/gestor/admin) é separada da publicação versionada
        // (admin). Gate cross-guard via permission: (PADRÃO 04-03/06). Publicar
        // gera NOVA versão por quatro olhos, nunca edição destrutiva da vigente.
        Route::middleware('permission:consultar-louos')->prefix('louos')->name('louos.')->group(function () {
            Route::get('/', [LouosController::class, 'index'])->name('index');
        });

        Route::middleware('permission:manter-louos')->prefix('louos')->name('louos.')->group(function () {
            Route::put('publicar', [LouosController::class, 'publish'])->name('publicar');

            // Sandbox de parametrização (HU-143): simular o impacto de um rascunho
            // contra cenários reais antes de publicar por quatro olhos. Simular e
            // publicar são manutenção (manter-louos). GET e POST compartilham o
            // caminho `simulacao` (um refresh recai na consulta sem o relatório).
            Route::get('simulacao', [LouosSandboxController::class, 'index'])->name('sandbox.index');
            Route::post('simulacao', [LouosSandboxController::class, 'simulate'])->name('sandbox.simular');
            Route::put('simulacao/publicar', [LouosSandboxController::class, 'publish'])->name('sandbox.publicar');
        });

        // Requisitos documentais (HU-067): cadastro administrável do modelo
        // "Requisito" (SIGVISA) e do vínculo N:N com CNAEs — a obrigatoriedade
        // documental por CNAE é DADO administrável (HU-014), consumido pelo
        // resolver da validação documental (08-08). Gate único manter-...; a
        // busca de CNAEs do picker reusa o CnaeSearchController (só ativos).
        Route::middleware('permission:manter-requisitos-documentais')->prefix('requisitos-documentais')->name('requisitos-documentais.')->group(function () {
            Route::get('/', [DocumentRequirementController::class, 'index'])->name('index');
            Route::get('cnaes-disponiveis', CnaeSearchController::class)->name('cnaes-disponiveis');
            Route::post('/', [DocumentRequirementController::class, 'store'])->name('store');
            Route::put('{requirement}', [DocumentRequirementController::class, 'update'])->name('update');
            Route::put('{requirement}/toggle', [DocumentRequirementController::class, 'toggle'])->name('toggle');
            Route::put('{requirement}/cnaes', [DocumentRequirementController::class, 'syncCnaes'])->name('cnaes');
        });

        // Tipos de serviço da solicitação (HU-061 RN-005): dado administrável
        // (CRUD) atrás de permissão própria — a listagem e o CRUD ficam sob
        // manter-tipos-servico (sem consulta separada). A desativação preserva
        // o histórico (toggle), nunca exclui o registro referenciado.
        Route::middleware('permission:manter-tipos-servico')->prefix('tipos-servico')->name('tipos-servico.')->group(function () {
            Route::get('/', [ViabilityServiceTypeController::class, 'index'])->name('index');
            Route::post('/', [ViabilityServiceTypeController::class, 'store'])->name('store');
            Route::put('{serviceType}', [ViabilityServiceTypeController::class, 'update'])->name('update');
            Route::put('{serviceType}/ativacao', [ViabilityServiceTypeController::class, 'toggleActivation'])->name('ativacao.update');
        });

        // Registro em contingência (HU-148): canal de operador na retaguarda e o
        // caminho REAL de operação enquanto o contrato Regin não chega (Fase 13).
        // Protocola pelo MESMO motor do canal normal (ProtocolarSolicitacaoService),
        // sem atalho decisório — muda só a origem (`contingencia`, auditada) e o
        // ator (operador). A busca de CNAEs do picker reusa o CnaeSearchController
        // (só ativos), como em requisitos-documentais.
        Route::middleware('permission:registrar-contingencia')->prefix('contingencia')->name('contingencia.')->group(function () {
            Route::get('/', [ContingenciaController::class, 'create'])->name('create');
            Route::get('cnaes-disponiveis', CnaeSearchController::class)->name('cnaes-disponiveis');
            Route::post('/', [ContingenciaController::class, 'store'])->name('store');
        });
    });
