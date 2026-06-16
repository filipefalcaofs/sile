<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompanySource;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\DocumentRequirement;
use App\Models\GeoLayer;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\LouosQuadro7Faixa;
use App\Models\Parameter;
use App\Models\RiskClassification;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Models\SanitaryRiskClassification;
use App\Models\Sector;
use App\Models\StandardText;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use Database\Seeders\ExpressoDevSeeder;
use Database\Seeders\ZonaFicticiaDevSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_completo_prepara_ambiente_de_desenvolvimento(): void
    {
        $this->seed();

        $this->assertSame(4, Role::query()->count());
        // 30 permissões (HU-013): as 19 base + as 5 da análise técnica
        // (analisar-processos, distribuir-processos, emitir-tvl,
        // encaminhar-malha-fina, manter-setores) + as 3 de auditoria e
        // compliance (consultar-auditoria, monitorar-lgpd, gerenciar-alertas-abuso)
        // + as 2 de relatórios (consultar-relatorios, relatorios.produtividade.nominal)
        // + a de configuração de e-mail (manter-config-email).
        $this->assertSame(30, Permission::query()->count());
        $this->assertNotNull(LegalTerm::current('lgpd'));
        $this->assertSame(1331, Cnae::query()->count());
        $this->assertSame(90, Parameter::query()->count());
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'cnaes')
                ->where('event', 'importacao-oficial')
                ->exists()
        );

        // Classificação de risco municipal (Decreto 32.636/2020): versão
        // vigente única + 1.331 classificações carregadas e auditadas.
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoMunicipal)->count());
        $this->assertSame(1331, RiskClassification::query()->count());
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'risco')
                ->where('event', 'importacao-classificacao-municipal')
                ->exists()
        );

        // Classificação de risco sanitário (VISA): dimensão SEPARADA, versão
        // vigente própria + 261 classificações e 67 condicionantes-pergunta.
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoSanitario)->count());
        $this->assertSame(261, SanitaryRiskClassification::query()->count());
        $this->assertSame(67, RiskCondicionante::query()->count());
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'risco')
                ->where('event', 'importacao-classificacao-sanitaria')
                ->exists()
        );

        // Quadros da LOUOS (Lei 9.148/2016): cada Quadro publica uma versão
        // vigente própria; o Quadro 7 carrega as 40 faixas reais e a importação
        // é auditada (RN-002). Quadros 10/11/11A modelados (carga oficial
        // pendente SEDUR), mas já versionados.
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro7)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro10)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11a)->count());
        $this->assertSame(40, LouosQuadro7Faixa::query()->count());
        $this->assertGreaterThan(0, LouosQuadro10Permissao::query()->count());
        $this->assertGreaterThan(0, LouosQuadro11CondicaoVia::query()->count());
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'louos')
                ->where('event', 'importacao-quadro7')
                ->exists()
        );

        $admin = User::query()->where('email', 'admin@sile.dev')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('administrador'));
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_seed_cria_cidadao_dev_com_empresas(): void
    {
        $this->seed();

        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->first();

        $this->assertNotNull($cidadao);
        $this->assertTrue($cidadao->hasRole('cidadao'));
        $this->assertNotNull($cidadao->email_verified_at);
        $this->assertTrue(
            LegalTermAcceptance::query()
                ->where('user_id', $cidadao->id)
                ->where('legal_term_id', LegalTerm::current('lgpd')->id)
                ->exists()
        );

        $this->assertGreaterThanOrEqual(3, Company::query()->count());
        $this->assertTrue(Company::query()->where('source', CompanySource::Manual)->exists());
        $this->assertTrue(Company::query()->where('source', CompanySource::Redesim)->exists());

        $vinculosAtivos = CompanyUser::query()
            ->where('user_id', $cidadao->id)
            ->whereNull('ended_at')
            ->count();
        $this->assertGreaterThanOrEqual(2, $vinculosAtivos);

        $temPrincipal = Company::query()
            ->whereHas('links', fn ($q) => $q->where('user_id', $cidadao->id)->whereNull('ended_at'))
            ->whereHas('cnaes', fn ($q) => $q->where('company_cnae.is_primary', true))
            ->exists();
        $this->assertTrue($temPrincipal);
    }

    public function test_seed_prepara_catalogo_e_solicitacoes_de_exemplo_da_fase_8(): void
    {
        $this->seed();

        // Catálogo de tipos de serviço (HU-061) — seed MÍNIMO com códigos
        // estáveis; a lista oficial é pendência SEDUR (substituível sem deploy).
        $this->assertSame(4, ViabilityServiceType::query()->count());
        $this->assertTrue(
            ViabilityServiceType::query()
                ->where('code', 'viabilidade-1-estabelecimento')
                ->where('active', true)
                ->exists()
        );

        // Requisitos documentais BASE (HU-067) referenciados pelo resolver por
        // code: fachada (sempre) e concessão (área pública). A obrigatoriedade
        // POR CNAE entra VAZIA — planilha oficial pendente SEDUR (honesto).
        $this->assertTrue(
            DocumentRequirement::query()->where('code', 'foto-fachada')->where('active', true)->exists()
        );
        $this->assertTrue(
            DocumentRequirement::query()->where('code', 'termo-concessao')->where('active', true)->exists()
        );
        $this->assertSame(0, DB::table('cnae_document_requirement')->count());

        // Solicitações de EXEMPLO do cidadão dev (dados fictícios, lógica REAL):
        // rascunho instruído, protocolada (número/timeline reais via serviço),
        // cancelada e contingência.
        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->firstOrFail();

        $rascunho = ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('status', ViabilityRequestStatus::Rascunho)
            ->first();
        $this->assertNotNull($rascunho, 'Esperava um rascunho instruído de exemplo.');
        $this->assertTrue($rascunho->primaryCnae()->exists());
        $this->assertNotNull($rascunho->property_polygon_geojson);

        $protocolada = ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('status', ViabilityRequestStatus::Protocolada)
            ->where('origin', ViabilityRequestOrigin::Portal)
            ->first();
        $this->assertNotNull($protocolada, 'Esperava uma solicitação protocolada de exemplo.');
        $this->assertMatchesRegularExpression('/^VIA-\d{4}-\d{6}$/', (string) $protocolada->protocol_number);
        // Timeline REAL: transição rascunho→protocolada gravada pelo serviço.
        $this->assertSame(
            1,
            $protocolada->transitions()->where('to_status', ViabilityRequestStatus::Protocolada)->count()
        );

        $cancelada = ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('status', ViabilityRequestStatus::Cancelada)
            ->first();
        $this->assertNotNull($cancelada, 'Esperava uma solicitação cancelada de exemplo.');
        $this->assertNotNull($cancelada->cancelled_at);

        $contingencia = ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('origin', ViabilityRequestOrigin::Contingencia)
            ->first();
        $this->assertNotNull($contingencia, 'Esperava uma solicitação de contingência de exemplo.');
        $this->assertSame(ViabilityRequestStatus::Protocolada, $contingencia->status);
        $this->assertNotNull($contingencia->contingency_reason);
    }

    public function test_seed_prepara_exemplos_navegaveis_do_fluxo_expresso(): void
    {
        $this->seed();

        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->firstOrFail();

        // Dois exemplos do fluxo expresso (dados fictícios, lógica REAL): um
        // sobre a zona fictícia (deferimento navegável) e um fora dela (em
        // análise honesto). Identificados pelo marcador estável.
        $deferimento = ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('address_reference', ExpressoDevSeeder::MARK_DEFERIDA)
            ->first();
        $this->assertNotNull($deferimento, 'Esperava o exemplo de deferimento do fluxo expresso.');
        $this->assertTrue($deferimento->primaryCnae()->where('code', '4712100')->exists());
        $this->assertNotNull($deferimento->property_polygon_geojson);

        $emAnalise = ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('address_reference', ExpressoDevSeeder::MARK_EM_ANALISE)
            ->first();
        $this->assertNotNull($emAnalise, 'Esperava o exemplo de em análise (sem zona) do fluxo expresso.');

        // Em SQLite a decisão do EXPRESSO não roda (reexecuta os motores
        // territoriais — exige PostGIS): os exemplos do expresso ficam
        // protocolados, sem ViabilityDecision, e a zona fictícia não é carregada.
        // Degradação honesta — o deferimento navegável (decisão + TVL sobre a zona
        // real) é provado em @group postgis (ExpressoSeedPostgisTest).
        $this->assertSame(ViabilityRequestStatus::Protocolada, $deferimento->status);
        $this->assertNull($deferimento->decision);
        $this->assertSame(ViabilityRequestStatus::Protocolada, $emAnalise->status);
        $this->assertNull($emAnalise->decision);
        // O EP12 (AuditoriaDevSeeder) acrescenta 2 decisões SQLite-safe pela
        // ANÁLISE TÉCNICA (territorial-agnóstica, em requerentes/empresas
        // dedicados): uma decidida com decision_trace e uma legada sem trace —
        // tornando a explicabilidade (HU-099) navegável no dev mesmo em SQLite.
        $this->assertSame(2, ViabilityDecision::query()->count());

        // Driver-aware: a camada de zona fictícia (geometria) não é carregada em
        // SQLite — a zona segue como pendente_fonte (degradação honesta).
        $this->assertSame(
            0,
            GeoLayer::query()->where('version', ZonaFicticiaDevSeeder::VERSION)->count(),
        );
    }

    public function test_seed_prepara_setores_textos_padrao_e_usuarios_da_analise(): void
    {
        $this->seed();

        // Setor da SEDUR (HU-138) — a caixa de distribuição da análise técnica,
        // criado como catálogo padrão (em produção o gestor mantém pela UI).
        $setor = Sector::query()->where('name', 'Análise Locacional')->first();
        $this->assertNotNull($setor, 'Esperava o setor padrão de análise locacional.');
        $this->assertTrue($setor->active);

        // Usuários dev da gestão (dados fictícios): um analista e um gestor,
        // ambos com o termo LGPD aceito e VINCULADOS ao setor — tornam a caixa,
        // a distribuição e a ficha navegáveis de ponta a ponta no dev.
        $analista = User::query()->where('email', 'analista@sile.dev')->first();
        $this->assertNotNull($analista, 'Esperava o analista dev (analista@sile.dev).');
        $this->assertTrue($analista->hasRole('analista'));
        $this->assertNotNull($analista->email_verified_at);
        $this->assertTrue($analista->sectors()->whereKey($setor->id)->exists());

        $gestor = User::query()->where('email', 'gestor@sile.dev')->first();
        $this->assertNotNull($gestor, 'Esperava o gestor dev (gestor@sile.dev).');
        $this->assertTrue($gestor->hasRole('gestor'));
        $this->assertTrue($gestor->sectors()->whereKey($setor->id)->exists());

        // Biblioteca de textos-padrão do parecer (HU-085): exemplos por categoria
        // (deferimento/indeferimento/condicionante/pendência), ativos e na versão
        // inicial — substituíveis pela SEDUR sem deploy (dados versionados).
        foreach (['deferimento', 'indeferimento', 'condicionante', 'pendencia'] as $categoria) {
            $texto = StandardText::query()->where('category', $categoria)->where('active', true)->first();
            $this->assertNotNull($texto, "Esperava um texto-padrão ativo na categoria {$categoria}.");
            $this->assertSame(1, $texto->version);
        }
    }

    public function test_seed_e_idempotente(): void
    {
        $this->seed();
        $this->seed();

        // Setores/usuários/textos-padrão da análise estáveis no re-seed
        // (firstOrCreate por chave estável — nada é duplicado).
        $this->assertSame(1, Sector::query()->where('name', 'Análise Locacional')->count());
        $this->assertSame(1, User::query()->where('email', 'analista@sile.dev')->count());
        $this->assertSame(1, User::query()->where('email', 'gestor@sile.dev')->count());
        $analistaId = User::query()->where('email', 'analista@sile.dev')->value('id');
        $setorId = Sector::query()->where('name', 'Análise Locacional')->value('id');
        $this->assertSame(
            1,
            DB::table('sector_user')->where('user_id', $analistaId)->where('sector_id', $setorId)->count(),
        );
        // Um texto-padrão por categoria (deferimento/indeferimento/condicionante/
        // pendência) — estável no re-seed (não duplica por content+category).
        $this->assertSame(4, StandardText::query()->count());

        $this->assertSame(1, User::query()->where('email', 'admin@sile.dev')->count());
        $this->assertSame(1, User::query()->where('email', 'cidadao@sile.dev')->count());
        $this->assertSame(4, Role::query()->count());
        $this->assertSame(1331, Cnae::query()->count());
        $this->assertSame(90, Parameter::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoMunicipal)->count());
        $this->assertSame(1331, RiskClassification::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoSanitario)->count());
        $this->assertSame(261, SanitaryRiskClassification::query()->count());
        $this->assertSame(67, RiskCondicionante::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro7)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro10)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11a)->count());
        $this->assertSame(40, LouosQuadro7Faixa::query()->count());
        // 3 empresas do cidadão (CompanySeeder) + 2 dedicadas do EP12
        // (AuditoriaDevSeeder: padrão de abuso e exemplos de decisão) = 5,
        // estáveis no re-seed (firstOrCreate por CNPJ).
        $this->assertSame(5, Company::query()->count());
        $this->assertSame(
            2,
            CompanyUser::query()
                ->where('user_id', User::query()->where('email', 'cidadao@sile.dev')->value('id'))
                ->whereNull('ended_at')
                ->count()
        );

        // Catálogo e solicitações de exemplo da Fase 8 estáveis no re-seed
        // (upsert/firstOrCreate por chave estável — nada é duplicado).
        $this->assertSame(4, ViabilityServiceType::query()->count());
        $this->assertSame(3, DocumentRequirement::query()->count());
        $this->assertSame(0, DB::table('cnae_document_requirement')->count());

        // 4 exemplos da Fase 8 (rascunho/protocolada/cancelada/contingência) +
        // 2 exemplos do fluxo expresso (deferimento/em análise) + 1 exemplo
        // dedicado de comunicação do EP11 (em_analise após o ciclo de pendência,
        // ComunicacaoDevSeeder) = 7, estáveis no re-seed.
        $cidadaoId = User::query()->where('email', 'cidadao@sile.dev')->value('id');
        $this->assertSame(7, ViabilityRequest::query()->where('requester_user_id', $cidadaoId)->count());
        // Os exemplos protocolados não ganham número novo a cada re-seed: 1 da
        // Fase 8 + 2 do expresso (em SQLite os exemplos do expresso ficam
        // protocolados — a decisão real exige PostGIS, testes @group postgis).
        $this->assertSame(
            3,
            ViabilityRequest::query()
                ->where('requester_user_id', $cidadaoId)
                ->where('origin', ViabilityRequestOrigin::Portal)
                ->where('status', ViabilityRequestStatus::Protocolada)
                ->count()
        );
    }

    public function test_seed_preserva_valor_de_parametro_administrado(): void
    {
        $this->seed();

        Parameter::query()
            ->where('key', 'ui.access_history.per_page')
            ->first()
            ->update(['value' => '7']);

        $this->seed();

        $this->assertSame(
            '7',
            Parameter::query()->where('key', 'ui.access_history.per_page')->first()->value
        );
    }

    public function test_admin_dev_acessa_gestao_apos_aceitar_termo(): void
    {
        $this->seed();

        // Ambientes independentes: a gestão tem login próprio (guard gestao).
        $this->post('/gestao/login', [
            'email' => 'admin@sile.dev',
            'password' => 'password',
        ])->assertRedirect('/gestao');

        $this->assertAuthenticated('gestao');

        $this->get('/gestao')->assertRedirect(route('portal.termo-lgpd.show'));

        $this->post('/portal/termo-lgpd', ['accepted' => true]);

        $this->get('/gestao')->assertOk();
    }
}
