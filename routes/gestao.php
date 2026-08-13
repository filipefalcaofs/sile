<?php

use App\Http\Controllers\ComunicacaoHistoricoController;
use App\Http\Controllers\Gestao\AbusoController;
use App\Http\Controllers\Gestao\AccessHistoryController;
use App\Http\Controllers\Gestao\AiConfigurationController;
use App\Http\Controllers\Gestao\AnalysisRecordController;
use App\Http\Controllers\Gestao\CondicionanteAutocompleteController;
use App\Http\Controllers\Gestao\AssistedAttendanceController;
use App\Http\Controllers\Gestao\AuditoriaController;
use App\Http\Controllers\Gestao\AuditoriaPreditivaController;
use App\Http\Controllers\Gestao\CaixaSetorController;
use App\Http\Controllers\Gestao\CnaeController;
use App\Http\Controllers\Gestao\ContingenciaController;
use App\Http\Controllers\Gestao\DashboardController;
use App\Http\Controllers\Gestao\DocumentRequirementController;
use App\Http\Controllers\Gestao\EmailLogController;
use App\Http\Controllers\Gestao\EmailServerController;
use App\Http\Controllers\Gestao\EnviarParaAnaliseController;
use App\Http\Controllers\Gestao\EscritorioVirtualDesvinculacaoController;
use App\Http\Controllers\Gestao\ExportacaoController;
use App\Http\Controllers\Gestao\GeocodeController;
use App\Http\Controllers\Gestao\HolidayController;
use App\Http\Controllers\Gestao\LgpdMonitorController;
use App\Http\Controllers\Gestao\LoginController;
use App\Http\Controllers\Gestao\LouosController;
use App\Http\Controllers\Gestao\LouosSandboxController;
use App\Http\Controllers\Gestao\MalhaFinaController;
use App\Http\Controllers\Gestao\ParameterController;
use App\Http\Controllers\Gestao\PrecedenteController;
use App\Http\Controllers\Gestao\ProcessoBuscaController;
use App\Http\Controllers\Gestao\ProcessoController;
use App\Http\Controllers\Gestao\ProcessoDecisaoController;
use App\Http\Controllers\Gestao\ProcessoPendenciaController;
use App\Http\Controllers\Gestao\RelatorioController;
use App\Http\Controllers\Gestao\ResultadoExpressoController;
use App\Http\Controllers\Gestao\RoleController;
use App\Http\Controllers\Gestao\SectorController;
use App\Http\Controllers\Gestao\StandardTextController;
use App\Http\Controllers\Gestao\TerritoryController;
use App\Http\Controllers\Gestao\TvlDocumentController;
use App\Http\Controllers\Gestao\UserManagementController;
use App\Http\Controllers\Gestao\ViabilityServiceTypeController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\Portal\CnaeSearchController;
use App\Http\Middleware\ResolveAssistedAttendance;
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

        // Central de notificações in-app (HU-090) — canal database nativo do
        // próprio servidor (escopo do dono). SEM permissão nova: a central é do
        // usuário autenticado, não da retaguarda. Mesmo controller do portal; o
        // badge de não-lidas é shared prop (HandleInertiaRequests).
        Route::get('notificacoes', [NotificationCenterController::class, 'index'])->name('notificacoes.index');
        Route::post('notificacoes/ler-todas', [NotificationCenterController::class, 'markAllAsRead'])->name('notificacoes.ler-todas');
        Route::post('notificacoes/{notification}/ler', [NotificationCenterController::class, 'markAsRead'])->name('notificacoes.ler');

        Route::get('acessos/{user}', AccessHistoryController::class)
            ->middleware('permission:consultar-acessos-de-qualquer-conta')
            ->name('acessos.show');

        // Trilha de auditoria (HU-098/100/101): consulta unificada server-driven
        // sobre activity_log (índices de 12-01) com filtros por período/usuário/
        // entidade/ação/resultado e fonte (atividade/alterações/acessos),
        // paginação no servidor e export CSV do conjunto filtrado (export pleno
        // XLSX/PDF → HU-131/Fase 15, bloqueado honesto). Gated por
        // consultar-auditoria (gestor/admin — 12-03); a própria consulta e o
        // export são auditados (meta-auditoria CA-02, personal_data) e o 403 é
        // auditado no ponto único (bootstrap/app.php). A rota estática `export`
        // vem ANTES do index para não ser capturada por wildcards futuros do
        // grupo. ÚNICO editor de routes/gestao.php na Wave 2; telas em 12-10.
        Route::middleware('permission:consultar-auditoria')->prefix('auditoria')->name('auditoria.')->group(function () {
            Route::get('export', [AuditoriaController::class, 'export'])->name('export');
            Route::get('/', [AuditoriaController::class, 'index'])->name('index');
        });

        // Monitoramento de conformidade LGPD (HU-102): painel que AGREGA fontes
        // REAIS — consentimentos (LegalTerm × LegalTermAcceptance), retenção
        // (retencao.access_logs.dias + último pruning da trilha; decisões FORA
        // do pruning por compliance) e acessos a dado pessoal
        // (activity_log.personal_data). MINIMIZADO (métricas, nunca PII crua) e
        // auditado (RN-002, personal_data); o 403 sem monitorar-lgpd é auditado
        // no ponto único (bootstrap/app.php). Direitos do titular
        // (eliminação/anonimização) ficam como pendência DPO — registrada, nunca
        // inventada. ÚNICO editor de routes/gestao.php na Wave 3; tela em 12-10.
        Route::middleware('permission:monitorar-lgpd')->prefix('lgpd')->name('lgpd.')->group(function () {
            Route::get('/', [LgpdMonitorController::class, 'index'])->name('index');
        });

        // Painel de alertas de abuso (HU-149): a superfície HUMANA de revisão dos
        // alertas gerados pelos detectores (12-06/12-08) — lista filtrável
        // (rule_key/severity/status/período) + indicador de EFETIVIDADE
        // (confirmados ÷ gerados, geral e por regra — RN-005, para calibrar
        // regras) e as ações confirmar/descartar com justificativa OBRIGATÓRIA
        // (RN-003), tudo auditado (RN-002). Anti-fachada CA-02: confirmar/
        // descartar muda SÓ o status do ALERTA — NUNCA transiciona o status do
        // processo nem mexe na malha fina já criada (ortogonal). Gated por
        // gerenciar-alertas-abuso; o 403 é auditado no ponto único
        // (bootstrap/app.php). Somente leitura + resolução; o motor não é tocado.
        // A rota estática index (/) vem ANTES das ações com {abuseAlert}; as
        // ações são POST de dois segmentos — não colidem com o index. ÚNICO editor
        // de routes/gestao.php na Wave 4; tela em 12-11.
        Route::middleware('permission:gerenciar-alertas-abuso')->prefix('abuso')->name('abuso.')->group(function () {
            Route::get('/', [AbusoController::class, 'index'])->name('index');
            Route::post('{abuseAlert}/confirmar', [AbusoController::class, 'confirmar'])->name('confirmar');
            Route::post('{abuseAlert}/descartar', [AbusoController::class, 'descartar'])->name('descartar');
        });

        // Auditoria Preditiva de Processos Expressos (Módulo 3): painel humano de
        // revisão das anomalias geradas pela varredura agendada (ia:auditoria-
        // preditiva). REUSA gerenciar-alertas-abuso (mesma governança antifraude);
        // confirmar/descartar muda SÓ o status da anomalia — nunca pune o processo
        // nem mexe na malha fina (ortogonal). Index (/) antes das ações POST com
        // {predictiveAnomaly}. O 403 é auditado no ponto único (bootstrap/app.php).
        Route::middleware('permission:gerenciar-alertas-abuso')->prefix('auditoria-preditiva')->name('auditoria-preditiva.')->group(function () {
            Route::get('/', [AuditoriaPreditivaController::class, 'index'])->name('index');
            Route::post('{predictiveAnomaly}/confirmar', [AuditoriaPreditivaController::class, 'confirmar'])->name('confirmar');
            Route::post('{predictiveAnomaly}/descartar', [AuditoriaPreditivaController::class, 'descartar'])->name('descartar');
        });

        // Relatórios e indicadores (HU-122..131/145): cada tela é servida com dado
        // REAL dos serviços route-free (15-03/05/06/07) e exporta pelo contrato
        // único via ?formato= (CSV/XLSX/PDF — HU-131/RN-004/009), delegando ao
        // ReportExporter (que audita — RN-008). A produtividade nominal só sai sob
        // relatorios.produtividade.nominal (gate no controller — RN-007); sem ela o
        // default conservador anonimiza e restringe ao próprio analista. Tudo gated
        // por consultar-relatorios e a consulta auditada (RN-002); o 403 é auditado
        // no ponto único (bootstrap/app.php). Cada relatório é uma rota estática
        // nomeada (sem wildcard {relatorio}); o download assinado das exportações
        // assíncronas (exportacoes/{exportFile}/download) é estático e vem ANTES dos
        // relatórios. Telas React em 15-13/15-14; RelatorioController é o ÚNICO
        // editor de routes/gestao.php nesta fase.
        Route::middleware('permission:consultar-relatorios')->prefix('relatorios')->name('relatorios.')->group(function () {
            // Download do arquivo da exportação assíncrona (15-02): URL TEMPORÁRIA
            // ASSINADA (signed) servindo o disco NÃO público por streaming, alvo do
            // link da Notification ExportacaoPronta. Rota estática — vem ANTES dos
            // relatórios (não há wildcard {relatorio}). Auditada no controller.
            Route::get('exportacoes/{exportFile}/download', [ExportacaoController::class, 'download'])
                ->middleware('signed')
                ->name('exportacoes.download');

            Route::get('indicadores', [RelatorioController::class, 'indicadores'])->name('indicadores');
            Route::get('tempo', [RelatorioController::class, 'tempo'])->name('tempo');
            Route::get('produtividade', [RelatorioController::class, 'produtividade'])->name('produtividade');
            Route::get('quedas', [RelatorioController::class, 'quedas'])->name('quedas');
            // Painel geoeconômico por bairro (Geo BI interno — Módulo 1): agregação
            // por bairro com decisões e taxa de deferimento; degrada honesto da zona
            // oficial (GIS pendente). Export pelo contrato único (SolicitacoesReportSource).
            Route::get('geo-bairro', [RelatorioController::class, 'geoBairro'])->name('geo-bairro');
            // Observatório de Saturação Locacional (Módulo 2): concentração por
            // bairro×CNAE vs capacidade recomendada (parâmetro HU-014), classificando
            // saturando/saturado. Degradação honesta sem capacidade/zona oficial.
            Route::get('saturacao', [RelatorioController::class, 'saturacao'])->name('saturacao');
            // Relatório sede × abrigados de escritório virtual (Plano R1): sede
            // (alvo do lock ativo) + abrigados da inscrição travada, recorte por nº
            // TVL da sede OU inscrição. Export pelo contrato único (?formato=).
            Route::get('escritorio-virtual', [RelatorioController::class, 'escritorioVirtual'])->name('escritorio-virtual');
            // Relatório SAPS "Tempo de Emissão de TVL" (Tela R2): página dedicada
            // (separada do KPI `tempo`) — tabela dos processos decididos no recorte
            // com o tempo Emissão−Abertura. Export pelo contrato único (?formato=).
            Route::get('tempo-emissao-tvl', [RelatorioController::class, 'tempoEmissaoTvl'])->name('tempo-emissao-tvl');
        });

        // Ficha única do CNAE: dados + classificação de risco municipal +
        // perguntas de condicionante sanitária, tudo na mesma tela. Consulta
        // granular separada da manutenção (HU-011 CA-04).
        Route::middleware('permission:consultar-cnaes')->group(function () {
            Route::get('cnaes', [CnaeController::class, 'index'])->name('cnaes.index');
            Route::get('cnaes/{cnae}/editar', [CnaeController::class, 'edit'])->name('cnaes.edit');
        });

        Route::middleware('permission:manter-cnaes')->group(function () {
            Route::get('cnaes/criar', [CnaeController::class, 'create'])->name('cnaes.create');
            Route::post('cnaes', [CnaeController::class, 'store'])->name('cnaes.store');
            Route::put('cnaes/{cnae}', [CnaeController::class, 'update'])->name('cnaes.update');
            Route::put('cnaes/{cnae}/situacao', [CnaeController::class, 'updateSituacao'])->name('cnaes.situacao.update');
            Route::delete('cnaes/{cnae}', [CnaeController::class, 'destroy'])->name('cnaes.destroy');
            Route::post('cnaes/{cnae}/condicionantes', [CnaeController::class, 'storeCondicionante'])->name('cnaes.condicionantes.store');
            Route::put('cnaes/{cnae}/condicionantes/{condicionante}', [CnaeController::class, 'updateCondicionante'])->name('cnaes.condicionantes.update');
            Route::delete('cnaes/{cnae}/condicionantes/{condicionante}', [CnaeController::class, 'destroyCondicionante'])->name('cnaes.condicionantes.destroy');
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

        // Servidores de e-mail administráveis (HU-014 / ConfigEmail): CRUD sob
        // permissão própria. A senha é criptografada e nunca reexibida; o teste
        // de conexão envia um e-mail real; o servidor padrão ativo controla o
        // envio via MailConfigServiceProvider.
        Route::middleware('permission:manter-config-email')->prefix('config-email')->name('config-email.')->group(function () {
            Route::get('/', [EmailServerController::class, 'index'])->name('index');
            Route::post('/', [EmailServerController::class, 'store'])->name('store');
            Route::put('{emailServer}', [EmailServerController::class, 'update'])->name('update');
            Route::delete('{emailServer}', [EmailServerController::class, 'destroy'])->name('destroy');
            Route::post('{emailServer}/testar', [EmailServerController::class, 'test'])->name('test');
        });

        // Provedores de IA administráveis (Fase 14, Onda 0 / HU-014): CRUD
        // multi-provider sob permissão própria, irmão do config-email. A api_key
        // é criptografada e nunca reexibida (DTO com masked_api_key); na edição,
        // chave em branco mantém a atual. O teste de conexão faz chamada HTTP
        // REAL ao provedor (anti-fachada), com anti-SSRF na base_url (https +
        // allowlist) e throttle parametrizado (limite interno de admin). A ponte
        // de runtime do SDK (laravel/ai) é pendência da Onda 1 — sem fachada.
        Route::middleware('permission:manter-config-ia')->prefix('config-ia')->name('config-ia.')->group(function () {
            Route::get('/', [AiConfigurationController::class, 'index'])->name('index');
            Route::post('/', [AiConfigurationController::class, 'store'])->name('store');
            Route::put('{aiConfiguration}', [AiConfigurationController::class, 'update'])->name('update');
            Route::delete('{aiConfiguration}', [AiConfigurationController::class, 'destroy'])->name('destroy');
            Route::post('{aiConfiguration}/testar', [AiConfigurationController::class, 'test'])->middleware('throttle:ai-connection-test')->name('test');
            Route::put('{aiConfiguration}/ativacao', [AiConfigurationController::class, 'toggleActivation'])->name('ativacao.update');
        });

        // Setores da SEDUR (HU-138): a "caixa de análise" da distribuição
        // (pré-requisito de 10-07/HU-144). CRUD administrável atrás de
        // manter-setores; o vínculo analista↔setor é N:N (RN-005 — um analista
        // cobre vários setores). NÃO há destroy: o setor não é excluído — só
        // inativado (RN-004; o toggle preserva histórico e vínculo). Telas em 10-17.
        Route::middleware('permission:manter-setores')->prefix('setores')->name('setores.')->group(function () {
            Route::get('/', [SectorController::class, 'index'])->name('index');
            Route::post('/', [SectorController::class, 'store'])->name('store');
            Route::put('{sector}', [SectorController::class, 'update'])->name('update');
            Route::put('{sector}/ativacao', [SectorController::class, 'toggleActivation'])->name('ativacao.update');
            Route::put('{sector}/analistas', [SectorController::class, 'syncAnalysts'])->name('analistas.update');
        });

        // Biblioteca de textos-padrão do parecer (HU-085): trechos versionados
        // pré-aprovados, administráveis atrás de manter-parametros (reuso da
        // permissão de admin de configuração — sem 6ª permissão; a leitura da
        // lista ativa pelo parecer fica sob analisar-processos em 10-09.
        // Pendência SEDUR: a coordenação pode exigir permissão própria). Editar o
        // conteúdo incrementa a versão (RN-005); inativar preserva o histórico
        // (sem destroy). Telas de console em 10-17.
        Route::middleware('permission:manter-parametros')->prefix('textos-padrao')->name('textos-padrao.')->group(function () {
            Route::get('/', [StandardTextController::class, 'index'])->name('index');
            Route::post('/', [StandardTextController::class, 'store'])->name('store');
            Route::put('{standardText}', [StandardTextController::class, 'update'])->name('update');
            Route::put('{standardText}/ativacao', [StandardTextController::class, 'toggleActivation'])->name('ativacao.update');
        });

        // Calendário de feriados (HU-137): dado administrável que o
        // BusinessDeadlineCalculator desconta no cálculo de dias úteis (HU-129). O
        // CRUD reusa manter-parametros (como setores/textos-padrão — sem permissão
        // nova; decisão registrada no SUMMARY). A data é única; NÃO há destroy —
        // inativar (ativacao) preserva o histórico/auditoria (RN-004). Toda
        // alteração é auditada (RN-002 via HasAuditoria); o 403 é auditado no ponto
        // único (bootstrap/app.php). As rotas estáticas (index/store) vêm ANTES do
        // wildcard {holiday}.
        Route::middleware('permission:manter-parametros')->prefix('feriados')->name('feriados.')->group(function () {
            Route::get('/', [HolidayController::class, 'index'])->name('index');
            Route::post('/', [HolidayController::class, 'store'])->name('store');
            Route::put('{holiday}', [HolidayController::class, 'update'])->name('update');
            Route::put('{holiday}/ativacao', [HolidayController::class, 'toggleActivation'])->name('ativacao.update');
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

        // Resultado do fluxo expresso (HU-076/HU-078): consulta da decisão
        // automática (deferida/indeferida) na retaguarda — lista filtrável e
        // detalhe imutável da ViabilityDecision. REUSO da permissão
        // consultar-solicitacoes: a decisão é parte da solicitação, então NÃO se
        // cria permissão nova (permanecem 19; decisão a validar com a SEDUR).
        // Somente leitura — a decisão é append-only (09-02/09-05).
        Route::middleware('permission:consultar-solicitacoes')->prefix('resultados-expresso')->name('resultados-expresso.')->group(function () {
            Route::get('/', [ResultadoExpressoController::class, 'index'])->name('index');
            Route::get('{viabilityRequest}', [ResultadoExpressoController::class, 'show'])->name('show');
        });

        // Enviar processo de TVL para análise (tela T06 — relatório de teste
        // SEDUR): gatilho MANUAL da retaguarda que leva um processo à fila da
        // análise técnica. Permissão DEDICADA (enviar-tvl-analise, 403 auditado no
        // ponto único); QUALQUER status é aceito (OPEN-F-2), vale para sede E
        // abrigado (OPEN-F-3) e o envio é idempotente (CA-E-03). Registrado ANTES
        // do grupo consultar-solicitacoes para que o caminho estático
        // `processos/enviar-para-analise` vença o wildcard `processos/{viabilityRequest}`.
        Route::middleware('permission:enviar-tvl-analise')->prefix('processos/enviar-para-analise')->name('processos.enviar-para-analise.')->group(function () {
            Route::get('/', [EnviarParaAnaliseController::class, 'index'])->name('index');
            Route::post('pesquisar', [EnviarParaAnaliseController::class, 'pesquisar'])->name('pesquisar');
            Route::post('enviar', [EnviarParaAnaliseController::class, 'enviar'])->name('enviar');
        });

        // Consulta de processos (HU-082) e fila do analista (HU-144) na
        // retaguarda: busca com os filtros completos do SAPS + analista +
        // categoria, paginação server-side (índices de 10-02/10-14), CSV simples
        // do conjunto filtrado (export pleno → HU-131/Fase 15), busca global
        // (Cmd+K) e fila priorizada por SLA (meus/setor). REUSO da permissão
        // consultar-solicitacoes (decisão de 10-01 — a consulta é parte da
        // solicitação; sem permissão nova). Somente leitura; auditada (RN-002); o
        // 403 é auditado no ponto único (bootstrap/app.php). As rotas estáticas
        // (fila, busca) vêm ANTES do show {viabilityRequest} para não serem
        // capturadas pelo wildcard. ÚNICO editor de routes/gestao.php na Wave 6.
        Route::middleware('permission:consultar-solicitacoes')->prefix('processos')->name('processos.')->group(function () {
            Route::get('/', [ProcessoController::class, 'index'])->name('index');
            Route::get('fila', [ProcessoController::class, 'fila'])->name('fila');
            Route::get('busca', ProcessoBuscaController::class)->name('busca');

            // Histórico unificado de comunicações do processo (HU-096): fonte
            // ÚNICA (ledger communications, todos os canais/tipos), REUSO da
            // permissão consultar-solicitacoes (a comunicação é parte da
            // solicitação — sem permissão nova). A gestão VÊ o error_message do
            // canal (diagnóstico interno). Auditada (RN-002). Rota com dois
            // segmentos — não colide com o {viabilityRequest} show.
            Route::get('{viabilityRequest}/comunicacoes', [ComunicacaoHistoricoController::class, 'gestao'])->name('comunicacoes');

            Route::get('{viabilityRequest}', [ProcessoController::class, 'show'])->name('show');
        });

        // Caixa do setor (HU-080/081): a fila da distribuição da análise técnica.
        // O analista vê e ASSUME os processos em_analise do(s) seu(s) setor(es)
        // (analisar-processos); o gestor DISTRIBUI — single ou lote (RN-007) — a
        // um analista do setor (distribuir-processos). A caixa NÃO tira o processo
        // do setor (RN-004) e o 403 é auditado no ponto único (bootstrap/app.php).
        // Telas em 10-16; ÚNICO editor de rotas da Wave 3.
        Route::prefix('caixa-setor')->name('caixa-setor.')->group(function () {
            Route::middleware('permission:analisar-processos')->group(function () {
                Route::get('/', [CaixaSetorController::class, 'index'])->name('index');
                Route::post('{viabilityRequest}/assumir', [CaixaSetorController::class, 'assumir'])->name('assumir');
            });

            Route::middleware('permission:distribuir-processos')->group(function () {
                Route::post('distribuir', [CaixaSetorController::class, 'distribuir'])->name('distribuir');
            });
        });

        // Ficha de análise técnica (HU-135/140/142): a superfície da análise
        // humana. Abre a revisão vigente (pré-analisada em 10-08), faz autosave do
        // rascunho (RN-008), finaliza tornando a revisão IMUTÁVEL (RN-003) e
        // materializando as divergências analista×motor (HU-140), cria uma nova
        // revisão para reedição/recálculo e compara duas revisões (diff — RN-007).
        // O painel de precedentes (HU-142) consome o PrecedentService (10-06).
        // Tudo gated por analisar-processos e auditado (RN-002); o 403 é auditado
        // no ponto único (bootstrap/app.php). A decisão (deferir/indeferir) NÃO
        // está aqui — é 10-10, a partir da ficha finalizada. ÚNICO editor de rotas
        // da Wave 4; telas em 10-17.
        Route::middleware('permission:analisar-processos')->prefix('processos/{viabilityRequest}')->name('processos.')->group(function () {
            Route::get('ficha', [AnalysisRecordController::class, 'show'])->name('ficha.show');
            Route::patch('ficha', [AnalysisRecordController::class, 'autosave'])->name('ficha.autosave');
            Route::post('ficha/finalizar', [AnalysisRecordController::class, 'finalizar'])->name('ficha.finalizar');
            Route::post('ficha/nova-revisao', [AnalysisRecordController::class, 'novaRevisao'])->name('ficha.nova-revisao');
            // Sugestão de minuta de parecer por IA (HU-118) — apoio, nunca decisão.
            Route::post('ficha/sugerir-parecer', [AnalysisRecordController::class, 'sugerirParecer'])->name('ficha.sugerir-parecer');
            Route::get('ficha/diff', [AnalysisRecordController::class, 'diff'])->name('ficha.diff');
            Route::get('precedentes', [PrecedenteController::class, 'show'])->name('precedentes');
        });

        // Autocomplete de condicionantes do cadastro VERSIONADO VIGENTE (T02 EV) —
        // consumido pela ficha de análise; itens da RuleVersion sanitária vigente
        // (RN-005). Não é por-processo, então fica fora do grupo processos/{...};
        // gated pela mesma permissão analisar-processos (403 auditado no ponto único).
        Route::middleware('permission:analisar-processos')
            ->get('condicionantes/autocomplete', CondicionanteAutocompleteController::class)
            ->name('condicionantes.autocomplete');

        // Ações do analista sobre o processo (Wave 7 — HU-083/086/087/088/089/
        // 132/136): os endpoints HTTP FINOS que expõem os serviços já testados das
        // Waves 5/6 — decidir/encerrar (AnaliseTecnicaDecisionService), abrir
        // pendência (PendenciaService), encaminhar à malha fina single+lote
        // (MalhaFinaService) e emitir/baixar o TVL (TvlPdfService). Toda a regra e
        // a auditoria vivem nos serviços; aqui só a casca gated/auditada que as
        // telas (10-16/10-17) acionam. Cada endpoint é gated pela permissão da
        // ação (403 auditado no ponto único — CA-04). O download do TVL é por URL
        // TEMPORÁRIA ASSINADA (signed) servindo o disco NÃO público por streaming
        // (HU-132 CA-02 — nunca URL pública, nunca ao cidadão). ÚNICO editor de
        // routes/gestao.php na Wave 7 (10-16/10-17 só páginas).
        Route::prefix('processos')->name('processos.')->group(function () {
            // Decidir (deferir/indeferir/encerrar) e abrir pendência — sob a
            // permissão da análise técnica (a regra de estado é do serviço).
            Route::middleware('permission:analisar-processos')->group(function () {
                Route::post('{viabilityRequest}/decidir', ProcessoDecisaoController::class)->name('decidir');
                Route::post('{viabilityRequest}/pendencias', [ProcessoPendenciaController::class, 'store'])->name('pendencias.store');
                Route::post('{viabilityRequest}/cancelar-convite', [ProcessoPendenciaController::class, 'cancelar'])->name('cancelar-convite');
                Route::post('{viabilityRequest}/status-analise', [ProcessoController::class, 'atualizarStatusAnalise'])->name('status-analise');
            });

            // Malha fina (encaminhar single+lote) — ortogonal ao status (RN-001):
            // atinge inclusive deferida. O lote é o caso geral (single = lote de um).
            Route::middleware('permission:encaminhar-malha-fina')->group(function () {
                Route::post('malha-fina', [MalhaFinaController::class, 'store'])->name('malha-fina.store');
            });

            // TVL: emitir (decisão deferida) + download por URL TEMPORÁRIA ASSINADA
            // (signed) servindo o disco NÃO público por streaming (HU-132 CA-02). As
            // rotas estáticas (malha-fina, tvl/...) convivem com o wildcard
            // {viabilityRequest} por método/estrutura distintos.
            Route::middleware('permission:emitir-tvl')->group(function () {
                Route::post('{viabilityRequest}/tvl', [TvlDocumentController::class, 'store'])->name('tvl.store');
                Route::get('tvl/{tvlDocument}/download', [TvlDocumentController::class, 'download'])
                    ->middleware('signed')
                    ->name('tvl.download');
                // Desvinculação manual da inscrição da sede de escritório virtual
                // (RN-EV-06) — gatilho de retaguarda até o REDESIM/cassação automatizarem.
                Route::post('{viabilityRequest}/desvincular-inscricao', EscritorioVirtualDesvinculacaoController::class)->name('desvincular-inscricao');
            });
        });

        // Atendimento presencial assistido (HU-150): canal de operador de balcão.
        // O atendente opera "em nome de" o cidadão presente, REUSANDO o mecanismo
        // de representação da Fase 1 — o middleware ResolveAssistedAttendance
        // (aplicado por classe, como o ResolveRepresentation do portal) popula o
        // MESMO Context/CurrentRepresentation, então a abertura grava
        // requester = cidadão e created_by = atendente, com a auditoria
        // registrando os dois (RN-001/RN-002). O vínculo expira e exige
        // reabertura (CA-03); o escopo fica atrás de atendimento-presencial.
        Route::middleware(['permission:atendimento-presencial', ResolveAssistedAttendance::class])
            ->prefix('atendimento')
            ->name('atendimento.')
            ->group(function () {
                Route::get('/', [AssistedAttendanceController::class, 'index'])->name('index');
                Route::post('/', [AssistedAttendanceController::class, 'store'])->name('store');
                Route::delete('/', [AssistedAttendanceController::class, 'destroy'])->name('destroy');
                Route::post('solicitacoes', [AssistedAttendanceController::class, 'storeSolicitacao'])->name('solicitacoes.store');
            });
    });
