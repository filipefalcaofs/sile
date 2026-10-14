<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Enums\TerritorialRuleEffect;
use App\Enums\TerritorialRuleType;
use App\Models\DecisionText;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga oficial das regras territoriais (RT-01 a RT-05) do pacote normativo
 * SEDUR de 2026-08-28 ("Especificação de Regras de Negócio e Tratamentos do
 * Sistema") — dado versionado do domínio `territorial`, no mesmo padrão dos
 * quadros LOUOS (rascunho → publicação; re-seed substitui as linhas da versão
 * vigente, sem duplicar).
 *
 * Decisões de carga registradas na spec 2026-08-28-regras-territoriais:
 *
 * - OPEN-RT-1: a lista do logradouro 212 tem 3831-9/99 em duplicidade com
 *   descrições divergentes e 4687-7/03 repetido — deduplicada por código
 *   (21 CNAEs únicos); a descrição oficial é a do cadastro de CNAE.
 * - OPEN-RT-2: o Hiperideal (item 11 de Itaigara) vem sem CODLOG — entra com
 *   5209 (Rua Anísio Teixeira, mesma via do item 10) pendente de confirmação.
 * - OPEN-RT-3: o documento grafa "AMB"; o motivo usa "ABM", sigla da LOUOS
 *   (art. 36) e do Decreto 31.168/2019.
 * - Poligonais VALIDADAS contra o GeoServer ao vivo em 2026-09-23 (do host de
 *   produção — a rede de dev é barrada pelo firewall): loteamentos em
 *   `parcelamento_poligonal:VM_PARCELAMENTO` (atributo PARCELAMENTO, nome em
 *   minúsculas — filtro ILIKE) e trechos da ABM na camada POLIGONAL
 *   `borda_maritima:VM_BORDA_MARITIMA_TRECHOS` (atributo TRECHOS, string;
 *   a `l_savam:VM_L_SAVAM_TRECHOS_ABM_2016` é LineString — não serve para
 *   ponto-em-polígono). ZEM/ZPAM/ZUE têm FeatureType própria confirmada.
 * - RT-05 (c) NÃO é seedada: "depósitos de produtos perigosos" é qualitativa
 *   e depende de mapeamento SEDUR (OPEN-RT-4) — bloqueada, nunca simulada.
 */
class TerritorialRuleSeeder extends Seeder
{
    /**
     * CNAEs proibidos no logradouro 212 (RT-01), deduplicados por código.
     *
     * @var list<string>
     */
    private const CNAES_RT01 = [
        '3811400', // Coleta de resíduos não perigosos
        '3831901', // Recuperação de sucatas de alumínio
        '3831999', // Recuperação de materiais metálicos, exceto alumínio
        '3832700', // Recuperação de materiais plásticos
        '3839499', // Recuperação de materiais não especificados anteriormente
        '4520006', // Serviços de borracharia para veículos automotores
        '4530704', // Comércio varejista de peças e acessórios usados e desmanche
        '4635403', // Comércio atacadista de bebidas com fracionamento
        '4635499', // Comércio atacadista de bebidas não especificadas
        '4681802', // Combustíveis por transportador retalhista (TRR)
        '4681803', // Combustíveis de origem vegetal, exceto álcool carburante
        '4681804', // Combustíveis de origem mineral em bruto
        '4681805', // Comércio atacadista de lubrificantes
        '4684299', // Outros produtos químicos e petroquímicos
        '4687701', // Resíduos de papel e papelão
        '4687702', // Resíduos e sucatas não metálicos, exceto papel/papelão
        '4687703', // Resíduos e sucatas metálicos
        '4689301', // Produtos da extração mineral, exceto combustíveis
        '4723700', // Comércio varejista de bebidas
        '4731800', // Comércio varejista de combustíveis
        '4732600', // Comércio varejista de lubrificantes
    ];

    /**
     * Allow-list de expresso do Loteamento Itaigara (RT-02) — CODLOG + número
     * de porta, na ordem do documento.
     *
     * @var list<array{rotulo: string, cod_log: string|null, logradouro_nome: string, numero_porta: string}>
     */
    private const ITAIGARA_ALLOWLIST = [
        ['rotulo' => 'Shopping Paseo', 'cod_log' => '518', 'logradouro_nome' => 'Rua Rubens Guelli', 'numero_porta' => '135'],
        ['rotulo' => 'Empresarial Elvira Vidal Orge', 'cod_log' => '518', 'logradouro_nome' => 'Rua Rubens Guelli', 'numero_porta' => '68'],
        ['rotulo' => 'Empresarial Itaigara', 'cod_log' => '518', 'logradouro_nome' => 'Rua Rubens Guelli', 'numero_porta' => '134'],
        ['rotulo' => 'Posto de Combustível', 'cod_log' => '2631', 'logradouro_nome' => 'Avenida Antônio Carlos Magalhães', 'numero_porta' => '1370'],
        ['rotulo' => 'Shopping da Cidade', 'cod_log' => '2631', 'logradouro_nome' => 'Avenida Antônio Carlos Magalhães', 'numero_porta' => '1298'],
        ['rotulo' => 'Tropical Center', 'cod_log' => '2631', 'logradouro_nome' => 'Avenida Antônio Carlos Magalhães', 'numero_porta' => '1116'],
        ['rotulo' => 'Pituba Parque Center', 'cod_log' => '2631', 'logradouro_nome' => 'Avenida Antônio Carlos Magalhães', 'numero_porta' => '1034'],
        ['rotulo' => 'Max Center', 'cod_log' => '2631', 'logradouro_nome' => 'Avenida Antônio Carlos Magalhães', 'numero_porta' => '846'],
        ['rotulo' => 'Shopping Itaigara', 'cod_log' => '2631', 'logradouro_nome' => 'Avenida Antônio Carlos Magalhães', 'numero_porta' => '656'],
        ['rotulo' => 'Boulevard 161', 'cod_log' => '5209', 'logradouro_nome' => 'Rua Anísio Teixeira', 'numero_porta' => '221'],
        // OPEN-RT-2: sem CODLOG no documento — 5209 presumido (mesma via do
        // item 10), pendente de confirmação SEDUR.
        ['rotulo' => 'Hiperideal', 'cod_log' => '5209', 'logradouro_nome' => 'Rua Anísio Teixeira', 'numero_porta' => '347'],
    ];

    /**
     * Allow-list da observação (a) do Quadro 10: ZCMu-2 da Estrada Velha do
     * Aeroporto (via 578) e dos bairros Calçada e São Cristóvão (art. 24,
     * alíneas f, g e i da LOUOS).
     *
     * @var list<array{rotulo: string, cod_log: string|null, logradouro_nome: string|null, bairro: string|null}>
     */
    private const QUADRO10_OBS_A = [
        ['rotulo' => 'Estrada Velha do Aeroporto', 'cod_log' => '578', 'logradouro_nome' => 'Avenida Aliomar Baleeiro', 'bairro' => null],
        ['rotulo' => 'Bairro Calçada', 'cod_log' => null, 'logradouro_nome' => null, 'bairro' => 'Calçada'],
        ['rotulo' => 'Bairro São Cristóvão', 'cod_log' => null, 'logradouro_nome' => null, 'bairro' => 'São Cristóvão'],
    ];

    /**
     * Allow-list da observação (b) do Quadro 10: as ZCLMu das oito vias
     * (art. 27, incisos XV, XX, XXI, XXIV, XXVII, XLV, XLVI e XLVII da LOUOS).
     *
     * @var list<array{rotulo: string, cod_log: string}>
     */
    private const QUADRO10_OBS_B = [
        ['rotulo' => 'Avenida Heitor Dias', 'cod_log' => '2075'],
        ['rotulo' => 'Avenida Suburbana (Afrânio Peixoto)', 'cod_log' => '4440'],
        ['rotulo' => 'Avenida General San Martin', 'cod_log' => '1387'],
        ['rotulo' => 'Avenida São Rafael', 'cod_log' => '2289'],
        ['rotulo' => 'Rodovia BA-528', 'cod_log' => '4926'],
        ['rotulo' => 'Avenida Ulysses Guimarães', 'cod_log' => '1469'],
        ['rotulo' => 'Estrada das Barreiras', 'cod_log' => '216'],
        ['rotulo' => 'Avenida Cardeal Brandão Vilela', 'cod_log' => '4661'],
    ];

    public function run(): void
    {
        $rules = app(RuleVersionService::class);

        $version = RuleVersion::vigente(RuleDomain::Territorial)->first();

        if ($version === null) {
            $draft = $rules->openDraft(
                RuleDomain::Territorial,
                'sedur-2026-08-28-regras-territoriais',
                'Pacote normativo SEDUR 2026-08-28 — Especificação de Regras de Negócio e Tratamentos do Sistema',
            );

            $version = $rules->publish($draft);
        }

        // Idempotente: substitui as regras da versão vigente (os filhos caem
        // em cascata) — re-seed não duplica nem deixa órfãs.
        TerritorialRule::query()->where('rule_version_id', $version->getKey())->delete();

        $textoIndeferimento = DecisionText::query()
            ->where('key', 'territorial.indeferimento.logradouro_cnae')
            ->first();

        $rt01 = TerritorialRule::create([
            'rule_version_id' => $version->getKey(),
            'codigo' => 'RT-01',
            'titulo' => 'Logradouro 212 — Avenida Lafayette Coutinho',
            'tipo' => TerritorialRuleType::LogradouroCnae,
            'efeito' => TerritorialRuleEffect::Indeferir,
            'cod_log' => '212',
            'logradouro_nome' => 'Avenida Lafayette Coutinho',
            'decision_text_id' => $textoIndeferimento?->getKey(),
            'base_legal' => 'art. 172-A da Lei Municipal nº 9.148/2016 (LOUOS), incluído pelo art. 11 da Lei Municipal nº 9.879/2025',
        ]);

        foreach (self::CNAES_RT01 as $cnae) {
            $rt01->cnaes()->create(['cnae' => $cnae]);
        }

        // RT-02 — TACs (art. 18, §3º, da LOUOS c/c art. 162 do PDDU). Poligonais validadas ao vivo
        // em 2026-09-23: o nome do loteamento é o atributo PARCELAMENTO (em
        // minúsculas) da camada de parcelamento — filtro ILIKE confirmado.
        TerritorialRule::create([
            'rule_version_id' => $version->getKey(),
            'codigo' => 'RT-02-VELA-BRANCA',
            'titulo' => 'Loteamento Vela Branca (TAC vigente)',
            'tipo' => TerritorialRuleType::PoligonalAnalise,
            'efeito' => TerritorialRuleEffect::EncaminharAnalise,
            'camada' => 'parcelamento_poligonal:VM_PARCELAMENTO',
            'camada_filtro' => "PARCELAMENTO ILIKE 'vela branca'",
            'motivo_analise' => 'Solicitação cadastrada no Loteamento Vela Branca.',
            'base_legal' => 'art. 18, §3º, da Lei Municipal nº 9.148/2016 (LOUOS), combinado com o art. 162 da Lei Municipal nº 9.069/2016 (PDDU)',
        ]);

        $itaigara = TerritorialRule::create([
            'rule_version_id' => $version->getKey(),
            'codigo' => 'RT-02-ITAIGARA',
            'titulo' => 'Loteamento Itaigara (TAC vigente) — allow-list de expresso',
            'tipo' => TerritorialRuleType::PoligonalAllowlistExpresso,
            'efeito' => TerritorialRuleEffect::PermitirExpresso,
            'camada' => 'parcelamento_poligonal:VM_PARCELAMENTO',
            'camada_filtro' => "PARCELAMENTO ILIKE 'itaigara'",
            'motivo_analise' => 'Solicitação cadastrada no Loteamento Itaigara.',
            'base_legal' => 'art. 18, §3º, da Lei Municipal nº 9.148/2016 (LOUOS), combinado com o art. 162 da Lei Municipal nº 9.069/2016 (PDDU)',
        ]);

        foreach (self::ITAIGARA_ALLOWLIST as $endereco) {
            $itaigara->addresses()->create($endereco);
        }

        // RT-03 — Zonas que sempre vão à análise. ZEM/ZPAM têm FeatureType
        // própria; a ABM usa a camada POLIGONAL de trechos (atributo TRECHOS,
        // string) — validada ao vivo em 2026-09-23. A l_savam homônima é
        // LineString e não serve para ponto-em-polígono.
        foreach ([
            ['codigo' => 'RT-03-ZEM', 'titulo' => 'ZEM — Zona de Exploração Mineral', 'camada' => 'louos_zem:VM_L_Z_USO_ZEM', 'filtro' => null, 'motivo' => 'Solicitação cadastrada na Zona de Exploração Mineral.', 'base_legal' => 'art. 31, caput e §2º, da Lei Municipal nº 9.148/2016 (LOUOS)'],
            ['codigo' => 'RT-03-ZPAM', 'titulo' => 'ZPAM — Zona de Proteção Ambiental', 'camada' => 'louos_zpam:VM_L_Z_USO_ZPAM', 'filtro' => null, 'motivo' => 'Solicitação cadastrada na Zona de Proteção Ambiental.', 'base_legal' => 'art. 33, §4º, da Lei Municipal nº 9.148/2016 (LOUOS)'],
            ['codigo' => 'RT-03-ABM', 'titulo' => 'ABM — Área de Borda Marítima, trechos 5 e 6', 'camada' => 'borda_maritima:VM_BORDA_MARITIMA_TRECHOS', 'filtro' => "TRECHOS IN ('5','6')", 'motivo' => 'Solicitação cadastrada nos Trechos 5 e 6 da ABM – Área de Borda Marítima, em conformidade com as disposições estabelecidas pelo Decreto Municipal nº 31.168/2019.', 'base_legal' => 'Decreto Municipal nº 31.168/2019'],
        ] as $zona) {
            TerritorialRule::create([
                'rule_version_id' => $version->getKey(),
                'codigo' => $zona['codigo'],
                'titulo' => $zona['titulo'],
                'tipo' => TerritorialRuleType::PoligonalAnalise,
                'efeito' => TerritorialRuleEffect::EncaminharAnalise,
                'camada' => $zona['camada'],
                'camada_filtro' => $zona['filtro'],
                'motivo_analise' => $zona['motivo'],
                'base_legal' => $zona['base_legal'],
            ]);
        }

        // RT-04 — Zonas de Uso Especial (art. 32): as dezessete subcategorias
        // compartilham um único motivo; a camada distingue para relatório.
        TerritorialRule::create([
            'rule_version_id' => $version->getKey(),
            'codigo' => 'RT-04-ZUE',
            'titulo' => 'Zonas de Uso Especial (art. 32, ZUE-1 a ZUE-17; incisos XIV a XVII incluídos pela Lei nº 9.879/2025)',
            'tipo' => TerritorialRuleType::PoligonalAnalise,
            'efeito' => TerritorialRuleEffect::EncaminharAnalise,
            'camada' => 'louos_zue:VM_L_Z_USO_ZUE',
            'motivo_analise' => 'Solicitação cadastrada em Zona de Uso Especial.',
            'base_legal' => 'art. 32, §3º, da Lei Municipal nº 9.148/2016 (LOUOS)',
        ]);

        // RT-05 — Observações (a) e (b) do Quadro 10: allow-lists de via (e
        // bairro, na (a)) dentro da zona alvo.
        $obsA = TerritorialRule::create([
            'rule_version_id' => $version->getKey(),
            'codigo' => 'RT-05-A',
            'titulo' => 'Quadro 10, observação (a) — ZCMu-2 permitidas',
            'tipo' => TerritorialRuleType::ViaAllowlistZona,
            'efeito' => TerritorialRuleEffect::RestringirUso,
            'zona_alvo' => 'ZCMu-2',
            'base_legal' => 'Art. 24, alíneas f, g e i, da Lei Municipal nº 9.148/2016 (LOUOS)',
        ]);

        foreach (self::QUADRO10_OBS_A as $local) {
            $obsA->addresses()->create($local);
        }

        $obsB = TerritorialRule::create([
            'rule_version_id' => $version->getKey(),
            'codigo' => 'RT-05-B',
            'titulo' => 'Quadro 10, observação (b) — ZCLMu permitidas',
            'tipo' => TerritorialRuleType::ViaAllowlistZona,
            'efeito' => TerritorialRuleEffect::RestringirUso,
            'zona_alvo' => 'ZCLMu',
            'base_legal' => 'Art. 27, incisos XV, XX, XXI, XXIV, XXVII, XLV, XLVI e XLVII, da Lei Municipal nº 9.148/2016 (LOUOS)',
        ]);

        foreach (self::QUADRO10_OBS_B as $via) {
            $obsB->addresses()->create($via);
        }

        app(AuditService::class)->log(
            logName: 'regras',
            event: 'importacao-regras-territoriais',
            description: 'Carga oficial das regras territoriais RT-01 a RT-05 (pacote SEDUR 2026-08-28)',
            properties: [
                'versao' => $version->version,
                'regras' => TerritorialRule::query()->where('rule_version_id', $version->getKey())->count(),
                'cnaes_rt01' => count(self::CNAES_RT01),
            ],
            rulesVersion: $version->version,
        );
    }
}
