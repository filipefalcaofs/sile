<?php

namespace Tests\Feature\Seeders;

use App\Enums\RuleDomain;
use App\Models\Activity;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\DocumentRequirement;
use App\Models\GeoLayer;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\Parameter;
use App\Models\PropertyType;
use App\Models\PropertyTypeAlias;
use App\Models\RiskClassification;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Models\SanitaryRiskClassification;
use App\Models\Sector;
use App\Models\StandardText;
use App\Models\TratamentoEnquadramento;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seed_completo_prepara_ambiente_de_desenvolvimento(): void
    {
        $this->seed();

        $this->assertSame(5, Role::query()->count());
        // 33 permissões (HU-013): as 17 base (sem consultar-risco/manter-risco,
        // consolidadas em consultar-cnaes/manter-cnaes) + as 5 da análise técnica
        // (analisar-processos, distribuir-processos, emitir-tvl,
        // encaminhar-malha-fina, manter-setores) + as 3 de auditoria e
        // compliance (consultar-auditoria, monitorar-lgpd, gerenciar-alertas-abuso)
        // + as 2 de relatórios (consultar-relatorios, relatorios.produtividade.nominal)
        // + a de configuração de e-mail (manter-config-email) + a de
        // configuração de IA (manter-config-ia — Fase 14 Onda 0) + a de envio
        // manual à análise (enviar-tvl-analise — tela T06 EV) + a de tipos de
        // imóvel (manter-tipos-imovel — parametrização do motor REGIN) + a de
        // gatilhos de risco (manter-gatilhos-risco — parametrização dos
        // gatilhos semi-expresso) + a de território (manter-territorio —
        // catálogo administrável de camadas do GeoServer) + a da ficha de
        // vistoria (preencher-ficha-vistoria).
        $this->assertSame(34, Permission::query()->count());
        $this->assertNotNull(LegalTerm::current('lgpd'));
        $this->assertSame(1331, Cnae::query()->count());
        // 100 parâmetros: 82 do catálogo base + 3 do Observatório de Saturação
        // + 1 (analise.convite.prazo_resposta_horas_uteis, Fase 2a)
        // (Módulo 2: relatorios.saturacao.*) + 3 da Auditoria Preditiva (Módulo 3:
        // features.ia_auditoria_preditiva + ia.auditoria_preditiva.janela/limiar)
        // + 2 do gatilho de sede de escritório virtual (Plano M1, Tarefa 3:
        // analise.escritorio_virtual.cnae_gatilho_sede/condicionante_sede)
        // + 1 do bloqueio de CNAE do abrigado (Plano M2, Tarefa 4:
        // analise.escritorio_virtual.mensagem_bloqueio_abrigado)
        // + 3 do envio manual à análise (tela T06: features.enviar_tvl_analise +
        // analise.enviar_analise.mensagem_nao_encontrado/mensagem_confirmacao)
        // + 3 dos bloqueios de constituição de sede (SDD escritorio-virtual-
        // constituicao-sede, Tarefa 2: mensagem_cnae_sede_em_abrigado/
        // mensagem_sede_duplicada/mensagem_cnae_fora_anexo_a)
        // + 1 da flag de encaminhamento à análise da sede (SDD escritorio-virtual-
        // constituicao-sede, Tarefa 3: analise.escritorio_virtual.flag_analise_sede)
        // + 1 da confirmação de perda da condição de sede (SDD escritorio-virtual-
        // exclusao-atividade, Tarefa 3: analise.escritorio_virtual.mensagem_confirma_perda_sede)
        // + 1 da pergunta geral de intenção de abrigo no passo do imóvel do
        // portal (SDD escritorio-virtual-telas-portal, Tarefa 1:
        // analise.escritorio_virtual.pergunta_geral)
        // + 1 da pergunta vinculada de intenção de sede no passo de
        // atividades do portal (SDD escritorio-virtual-telas-portal, Tarefa 3:
        // analise.escritorio_virtual.pergunta_vinculada)
        // + 1 dos atributos de nome da zona na feição (Fase 3 parametrização,
        // item 3.4: geo.zona.atributos_nome).
        // + 3 da Fase 5 (janela de duplicidade + pesos e cortes da auditoria
        // preditiva).
        // + 1 do setor de triagem da análise (analise.setor_triagem_id — elo
        // motor → caixa do setor, 2026-09-20).
        $this->assertSame(124, Parameter::query()->count());
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'cnaes')
                ->where('event', 'importacao-oficial')
                ->exists()
        );

        // Classificação de risco municipal (Decreto 41.758/2026): versão
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
        // vigente própria + 261 classificações. Sem condicionantes-pergunta
        // (e-mail SEDUR 21/09/2026, item 4).
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoSanitario)->count());
        $this->assertSame(261, SanitaryRiskClassification::query()->count());
        $this->assertSame(0, RiskCondicionante::query()->count());
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'risco')
                ->where('event', 'importacao-classificacao-sanitaria')
                ->exists()
        );

        // Quadros territoriais da LOUOS + planilha de tratamento: cada domínio
        // publica uma versão vigente própria. A planilha 20.08.26 (1.332 CNAEs /
        // 2.850 linhas) substitui o Quadro 7 como fonte do enquadramento.
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoTratamento)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro10)->count());
        $this->assertDatabaseMissing('rule_versions', ['domain' => 'louos_quadro11']);
        $this->assertDatabaseMissing('rule_versions', ['domain' => 'louos_quadro7']);
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11a)->count());
        $this->assertSame(1332, TratamentoEnquadramento::query()->distinct()->count('cnae'));
        $this->assertSame(2850, TratamentoEnquadramento::query()->count());
        $this->assertGreaterThan(0, LouosQuadro10Permissao::query()->count());
        $this->assertGreaterThan(0, LouosQuadro11CondicaoVia::query()->count());
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'tratamento')
                ->where('event', 'importacao-planilha')
                ->exists()
        );

        // Tipos de imóvel do REGIN (catálogo SEDUR 2026-08-26) — dado
        // administrável que o motor consulta: sem carga, TODO valor do REGIN
        // vira desconhecido e o processo degrada para análise.
        $this->assertSame(5, PropertyType::query()->count());
        $galpao = PropertyType::query()->where('code', 'galpao')->first();
        $this->assertNotNull($galpao, 'Esperava o tipo de imóvel galpao semeado.');
        $this->assertTrue($galpao->drives_rule);
        $this->assertTrue($galpao->active);
        $this->assertContains('galpao', $galpao->aliases->pluck('alias')->all());
    }

    public function test_seed_prepara_setores_textos_padrao_e_usuarios_da_analise(): void
    {
        $this->seed();

        // Setor da SEDUR (HU-138) — a caixa de distribuição da análise técnica,
        // criado como catálogo padrão (em produção o gestor mantém pela UI).
        $setor = Sector::query()->where('name', 'Análise Locacional')->first();
        $this->assertNotNull($setor, 'Esperava o setor padrão de análise locacional.');
        $this->assertTrue($setor->active);

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
        $this->assertSame(0, User::query()->count());
        // Um texto-padrão por categoria (deferimento/indeferimento/condicionante/
        // pendência) — estável no re-seed (não duplica por content+category).
        $this->assertSame(4, StandardText::query()->count());

        $this->assertSame(5, Role::query()->count());
        $this->assertSame(1331, Cnae::query()->count());
        $this->assertSame(124, Parameter::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoMunicipal)->count());
        $this->assertSame(1331, RiskClassification::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoSanitario)->count());
        $this->assertSame(261, SanitaryRiskClassification::query()->count());
        $this->assertSame(0, RiskCondicionante::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoTratamento)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro10)->count());
        $this->assertDatabaseMissing('rule_versions', ['domain' => 'louos_quadro11']);
        $this->assertDatabaseMissing('rule_versions', ['domain' => 'louos_quadro7']);
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11a)->count());
        $this->assertSame(1332, TratamentoEnquadramento::query()->distinct()->count('cnae'));
        $this->assertSame(2850, TratamentoEnquadramento::query()->count());
        // Tipos de imóvel estáveis no re-seed (updateOrCreate por code,
        // firstOrCreate por alias) — nem duplica nem perde o catálogo.
        $this->assertSame(5, PropertyType::query()->count());
        $this->assertSame(5, PropertyTypeAlias::query()->count());
        $this->assertSame(0, Company::query()->count());

        $this->assertSame(4, ViabilityServiceType::query()->count());
        $this->assertSame(3, DocumentRequirement::query()->count());
        $this->assertSame(0, DB::table('cnae_document_requirement')->count());
        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_seed_preserva_valor_de_parametro_administrado(): void
    {
        $this->seed();

        Parameter::query()
            ->where('key', 'security.login.max_attempts')
            ->first()
            ->update(['value' => '3']);

        $this->seed();

        $this->assertSame(
            '3',
            Parameter::query()->where('key', 'security.login.max_attempts')->first()->value
        );
    }
}
