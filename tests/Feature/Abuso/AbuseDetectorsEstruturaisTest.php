<?php

namespace Tests\Feature\Abuso;

use App\Enums\ViabilityRequestStatus;
use App\Models\AbuseAlert;
use App\Models\Cnae;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Abuso\AbuseDetectionService;
use App\Services\Abuso\AbuseFinding;
use App\Services\Abuso\DetectionWindow;
use App\Services\Abuso\Detectors\CondicionanteEvasaoDetector;
use App\Services\Abuso\Detectors\InscricaoAtividadesIncompativeisDetector;
use App\Services\Abuso\Detectors\PoligonoRepetidoDetector;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Detectores ESTRUTURAIS de abuso (HU-149, 12-08) sobre dado REAL — SEM IA.
 * Espelham a forma dos detectores de volume (12-06): cada Strategy varre a janela
 * parametrizável e emite um AbuseFinding SÓ acima do critério determinístico, com
 * fingerprint ESTÁVEL (idempotência da 12-03) e evidence honesta. A janela é
 * respeitada (dado fora não conta) e NENHUM detector pune/transiciona (RN-001).
 */
class AbuseDetectorsEstruturaisTest extends TestCase
{
    use RefreshDatabase;

    private function janela(int $dias = 30): DetectionWindow
    {
        return DetectionWindow::lastDays($dias);
    }

    /**
     * @param  iterable<AbuseFinding>  $findings
     * @return list<AbuseFinding>
     */
    private function lista(iterable $findings): array
    {
        return is_array($findings) ? array_values($findings) : iterator_to_array($findings, false);
    }

    /**
     * Polígono quadrilátero fechado (anel exterior) — fonte de verdade portável.
     *
     * @return array<string, mixed>
     */
    private function poligono(float $oeste, float $sul, float $passo = 0.0002): array
    {
        $leste = $oeste + $passo;
        $norte = $sul + $passo;

        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [$oeste, $sul],
                [$oeste, $norte],
                [$leste, $norte],
                [$leste, $sul],
                [$oeste, $sul],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function solicitacao(array $attributes): ViabilityRequest
    {
        return ViabilityRequest::factory()->create($attributes);
    }

    // === PoligonoRepetidoDetector ===

    public function test_poligono_repetido_emite_finding_quando_mesmo_poligono_em_enderecos_distintos(): void
    {
        $poligono = $this->poligono(-38.5108, -12.9711);

        $a = $this->solicitacao([
            'property_polygon_geojson' => $poligono,
            'address_street' => 'Rua A', 'address_number' => '100',
            'address_neighborhood' => 'Centro', 'address_zip' => '40000001',
        ]);
        $b = $this->solicitacao([
            'property_polygon_geojson' => $poligono,
            'address_street' => 'Rua B', 'address_number' => '200',
            'address_neighborhood' => 'Comércio', 'address_zip' => '40000002',
        ]);

        $findings = $this->lista(app(PoligonoRepetidoDetector::class)->detect($this->janela()));

        $this->assertCount(1, $findings);
        $finding = $findings[0];
        $this->assertSame('poligono_repetido', $finding->ruleKey);
        $this->assertSame(2, $finding->evidence['enderecos_distintos']);
        $this->assertNotSame('', $finding->evidence['poligono_hash']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $finding->evidence['ids']);
        $this->assertContains($finding->viabilityRequestId, [$a->id, $b->id]);
    }

    public function test_poligono_repetido_nao_emite_quando_mesmo_endereco(): void
    {
        // Mesmo polígono no MESMO endereço (recadastro do próprio imóvel) não é abuso.
        $poligono = $this->poligono(-38.5108, -12.9711);
        $endereco = [
            'property_polygon_geojson' => $poligono,
            'address_street' => 'Rua A', 'address_number' => '100',
            'address_neighborhood' => 'Centro', 'address_zip' => '40000001',
        ];

        $this->solicitacao($endereco);
        $this->solicitacao($endereco);

        $findings = $this->lista(app(PoligonoRepetidoDetector::class)->detect($this->janela()));

        $this->assertCount(0, $findings);
    }

    public function test_poligono_repetido_nao_emite_para_poligonos_distintos(): void
    {
        $this->solicitacao(['property_polygon_geojson' => $this->poligono(-38.5108, -12.9711)]);
        $this->solicitacao(['property_polygon_geojson' => $this->poligono(-38.4000, -12.8000)]);

        $findings = $this->lista(app(PoligonoRepetidoDetector::class)->detect($this->janela()));

        $this->assertCount(0, $findings);
    }

    public function test_poligono_repetido_agrupa_poligonos_equivalentes_apesar_da_ordem_dos_vertices(): void
    {
        // Mesmo anel, vértice inicial diferente (rotação) → chave normalizada igual.
        $base = [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.5108, -12.9711],
                [-38.5108, -12.9709],
                [-38.5106, -12.9709],
                [-38.5106, -12.9711],
                [-38.5108, -12.9711],
            ]],
        ];
        $rotacionado = [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.5106, -12.9709],
                [-38.5106, -12.9711],
                [-38.5108, -12.9711],
                [-38.5108, -12.9709],
                [-38.5106, -12.9709],
            ]],
        ];

        $this->solicitacao([
            'property_polygon_geojson' => $base,
            'address_street' => 'Rua A', 'address_number' => '1',
            'address_neighborhood' => 'Centro', 'address_zip' => '40000001',
        ]);
        $this->solicitacao([
            'property_polygon_geojson' => $rotacionado,
            'address_street' => 'Rua B', 'address_number' => '2',
            'address_neighborhood' => 'Comércio', 'address_zip' => '40000002',
        ]);

        $findings = $this->lista(app(PoligonoRepetidoDetector::class)->detect($this->janela()));

        $this->assertCount(1, $findings);
        $this->assertSame(2, $findings[0]->evidence['enderecos_distintos']);
    }

    public function test_poligono_repetido_respeita_a_janela(): void
    {
        $poligono = $this->poligono(-38.5108, -12.9711);

        $this->solicitacao([
            'property_polygon_geojson' => $poligono,
            'address_street' => 'Rua A', 'address_zip' => '40000001',
        ]);
        $this->solicitacao([
            'property_polygon_geojson' => $poligono,
            'address_street' => 'Rua B', 'address_zip' => '40000002',
            'created_at' => now()->subDays(40),
        ]);

        $findings = $this->lista(app(PoligonoRepetidoDetector::class)->detect($this->janela(30)));

        $this->assertCount(0, $findings);
    }

    public function test_poligono_repetido_fingerprint_e_estavel(): void
    {
        $poligono = $this->poligono(-38.5108, -12.9711);
        $this->solicitacao([
            'property_polygon_geojson' => $poligono,
            'address_street' => 'Rua A', 'address_zip' => '40000001',
        ]);
        $this->solicitacao([
            'property_polygon_geojson' => $poligono,
            'address_street' => 'Rua B', 'address_zip' => '40000002',
        ]);

        $primeiro = $this->lista(app(PoligonoRepetidoDetector::class)->detect($this->janela()))[0];
        $segundo = $this->lista(app(PoligonoRepetidoDetector::class)->detect($this->janela()))[0];

        $this->assertSame($primeiro->fingerprint, $segundo->fingerprint);
        $this->assertNotSame('', $primeiro->fingerprint);
    }

    // === InscricaoAtividadesIncompativeisDetector ===

    public function test_inscricao_emite_finding_para_cnaes_primarios_distintos_na_mesma_inscricao(): void
    {
        $padaria = Cnae::factory()->create();
        $oficina = Cnae::factory()->create();

        $a = $this->solicitacao(['property_registration' => '123.456.789-0']);
        $a->cnaes()->attach($padaria->id, ['is_primary' => true]);

        $b = $this->solicitacao(['property_registration' => '123.456.789-0']);
        $b->cnaes()->attach($oficina->id, ['is_primary' => true]);

        $findings = $this->lista(app(InscricaoAtividadesIncompativeisDetector::class)->detect($this->janela()));

        $this->assertCount(1, $findings);
        $finding = $findings[0];
        $this->assertSame('inscricao_atividades_incompativeis', $finding->ruleKey);
        $this->assertSame('123.456.789-0', $finding->evidence['inscricao']);
        $this->assertSame(2, $finding->evidence['cnaes_distintos']);
        $this->assertEqualsCanonicalizing([$padaria->code, $oficina->code], $finding->evidence['cnaes_primarios']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $finding->evidence['ids']);
    }

    public function test_inscricao_nao_emite_para_mesma_atividade(): void
    {
        $cnae = Cnae::factory()->create();

        $a = $this->solicitacao(['property_registration' => '123.456.789-0']);
        $a->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $b = $this->solicitacao(['property_registration' => '123.456.789-0']);
        $b->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $findings = $this->lista(app(InscricaoAtividadesIncompativeisDetector::class)->detect($this->janela()));

        $this->assertCount(0, $findings);
    }

    public function test_inscricao_ignora_canceladas_e_inscricao_nula(): void
    {
        $padaria = Cnae::factory()->create();
        $oficina = Cnae::factory()->create();

        // Inscrição nula nunca agrupa (a maioria dos imóveis informais).
        $this->solicitacao(['property_registration' => null]);

        // Mesma inscrição, mas a 2ª está cancelada → não há simultaneidade ativa.
        $a = $this->solicitacao(['property_registration' => '999.999.999-9']);
        $a->cnaes()->attach($padaria->id, ['is_primary' => true]);

        $b = $this->solicitacao([
            'property_registration' => '999.999.999-9',
            'status' => ViabilityRequestStatus::Cancelada,
        ]);
        $b->cnaes()->attach($oficina->id, ['is_primary' => true]);

        $findings = $this->lista(app(InscricaoAtividadesIncompativeisDetector::class)->detect($this->janela()));

        $this->assertCount(0, $findings);
    }

    public function test_inscricao_respeita_a_janela(): void
    {
        $padaria = Cnae::factory()->create();
        $oficina = Cnae::factory()->create();

        $a = $this->solicitacao(['property_registration' => '123.456.789-0']);
        $a->cnaes()->attach($padaria->id, ['is_primary' => true]);

        $b = $this->solicitacao([
            'property_registration' => '123.456.789-0',
            'created_at' => now()->subDays(40),
        ]);
        $b->cnaes()->attach($oficina->id, ['is_primary' => true]);

        $findings = $this->lista(app(InscricaoAtividadesIncompativeisDetector::class)->detect($this->janela(30)));

        $this->assertCount(0, $findings);
    }

    public function test_inscricao_fingerprint_e_estavel(): void
    {
        $padaria = Cnae::factory()->create();
        $oficina = Cnae::factory()->create();

        $a = $this->solicitacao(['property_registration' => '123.456.789-0']);
        $a->cnaes()->attach($padaria->id, ['is_primary' => true]);

        $b = $this->solicitacao(['property_registration' => '123.456.789-0']);
        $b->cnaes()->attach($oficina->id, ['is_primary' => true]);

        $detector = app(InscricaoAtividadesIncompativeisDetector::class);
        $primeiro = $this->lista($detector->detect($this->janela()))[0];
        $segundo = $this->lista($detector->detect($this->janela()))[0];

        $this->assertSame($primeiro->fingerprint, $segundo->fingerprint);
        $this->assertNotSame('', $primeiro->fingerprint);
    }

    public function test_keys_dos_detectores_estruturais(): void
    {
        $this->assertSame('poligono_repetido', app(PoligonoRepetidoDetector::class)->key());
        $this->assertSame('inscricao_atividades_incompativeis', app(InscricaoAtividadesIncompativeisDetector::class)->key());
        $this->assertSame('condicionante_evasao', app(CondicionanteEvasaoDetector::class)->key());
    }

    // === CondicionanteEvasaoDetector ===

    /**
     * Snapshot de simulação no shape REAL persistido (ResolvedViability::toSnapshot
     * → consulta.risco.sanitario.condicionantes_perguntas). Cada item de $respostas
     * é ['resposta' => bool|null, 'acionou' => bool]: a resposta evasiva é a que
     * NÃO aciona a reclassificação para análise (acionou=false com resposta dada).
     *
     * @param  list<array{resposta: bool|null, acionou: bool}>  $respostas
     * @return array<string, mixed>
     */
    private function snapshotComCondicionantes(array $respostas): array
    {
        $perguntas = [];
        foreach ($respostas as $indice => $item) {
            $perguntas[] = [
                'condicionante_id' => $indice + 1,
                'pergunta' => 'O produto é artesanal?',
                'resposta' => $item['resposta'],
                'acionou' => $item['acionou'],
                'reclassifica_para' => 'alto',
                'fundamento' => 'Caso não seja artesanal, será considerado Alto Risco.',
            ];
        }

        return [
            'ponto' => null,
            'area_m2' => null,
            'por_cnae' => [[
                'cnae' => '4721102',
                'cnae_formatado' => '4721-1/02',
                'is_primary' => true,
                'tendencia' => 'permitido',
                'tendencia_label' => 'Permitido',
                'consulta' => [
                    'risco' => [
                        'sanitario' => [
                            'status' => 'classificado',
                            'condicionantes_perguntas' => $perguntas,
                        ],
                    ],
                ],
            ]],
        ];
    }

    /**
     * Solicitação cuja simulação respondeu condicionantes evitando a análise
     * (resposta dada, nenhuma acionou) — o padrão evasivo do requerente.
     */
    private function processoEvasivo(User $requerente, ?CarbonInterface $criadoEm = null): ViabilityRequest
    {
        return $this->solicitacao([
            'requester_user_id' => $requerente->id,
            'simulation_snapshot' => $this->snapshotComCondicionantes([
                ['resposta' => false, 'acionou' => false],
            ]),
            'created_at' => $criadoEm ?? now(),
        ]);
    }

    public function test_condicionante_evasao_emite_para_requerente_que_sempre_evade(): void
    {
        // Requerente com 3 processos cujas condicionantes SEMPRE evitam a análise
        // (acima do mínimo provisório de 3) e NENHUMA resposta acionadora → alerta.
        $requerente = User::factory()->create();
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->processoEvasivo($requerente)->id;
        }

        // Outro requerente que respondeu honestamente (acionou) — não deve alertar.
        $honesto = User::factory()->create();
        $this->solicitacao([
            'requester_user_id' => $honesto->id,
            'simulation_snapshot' => $this->snapshotComCondicionantes([
                ['resposta' => true, 'acionou' => true],
            ]),
        ]);

        $findings = $this->lista(app(CondicionanteEvasaoDetector::class)->detect($this->janela()));

        $this->assertCount(1, $findings);
        $finding = $findings[0];
        $this->assertSame('condicionante_evasao', $finding->ruleKey);
        $this->assertSame($requerente->id, $finding->evidence['requester_user_id']);
        $this->assertSame(3, $finding->evidence['processos']);
        $this->assertEqualsCanonicalizing($ids, $finding->evidence['ids']);
        $this->assertInstanceOf(User::class, $finding->subject);
        $this->assertTrue($finding->subject->is($requerente));
    }

    public function test_condicionante_evasao_nao_emite_quando_ha_resposta_honesta(): void
    {
        // Mesmo com 3 evasivos, UMA resposta acionadora (escolheu a opção que leva
        // à análise) descaracteriza o "sempre evita" → sem alerta.
        $requerente = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->processoEvasivo($requerente);
        }
        $this->solicitacao([
            'requester_user_id' => $requerente->id,
            'simulation_snapshot' => $this->snapshotComCondicionantes([
                ['resposta' => true, 'acionou' => true],
            ]),
        ]);

        $findings = $this->lista(app(CondicionanteEvasaoDetector::class)->detect($this->janela()));

        $this->assertCount(0, $findings);
    }

    public function test_condicionante_evasao_nao_emite_abaixo_do_minimo(): void
    {
        $requerente = User::factory()->create();
        $this->processoEvasivo($requerente);
        $this->processoEvasivo($requerente);

        $findings = $this->lista(app(CondicionanteEvasaoDetector::class)->detect($this->janela()));

        $this->assertCount(0, $findings);
    }

    public function test_condicionante_evasao_ignora_processos_sem_resposta(): void
    {
        // Snapshot sem resposta (resposta null) não é sinal de evasão — a simulação
        // pode nem ter capturado condicionante. Não conta para o mínimo.
        $requerente = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->solicitacao([
                'requester_user_id' => $requerente->id,
                'simulation_snapshot' => $this->snapshotComCondicionantes([
                    ['resposta' => null, 'acionou' => false],
                ]),
            ]);
        }

        $findings = $this->lista(app(CondicionanteEvasaoDetector::class)->detect($this->janela()));

        $this->assertCount(0, $findings);
    }

    public function test_condicionante_evasao_respeita_a_janela(): void
    {
        // 2 evasivos na janela + 1 fora (40 dias). Dentro só 2 (< 3) → sem alerta.
        $requerente = User::factory()->create();
        $this->processoEvasivo($requerente);
        $this->processoEvasivo($requerente);
        $this->processoEvasivo($requerente, now()->subDays(40));

        $findings = $this->lista(app(CondicionanteEvasaoDetector::class)->detect($this->janela(30)));

        $this->assertCount(0, $findings);
    }

    public function test_condicionante_evasao_fingerprint_e_estavel(): void
    {
        $requerente = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->processoEvasivo($requerente);
        }

        $detector = app(CondicionanteEvasaoDetector::class);
        $primeiro = $this->lista($detector->detect($this->janela()))[0];
        $segundo = $this->lista($detector->detect($this->janela()))[0];

        $this->assertSame($primeiro->fingerprint, $segundo->fingerprint);
        $this->assertNotSame('', $primeiro->fingerprint);
    }

    // === Pipeline: AbuseDetectionService itera os 5 detectores ===

    private function ligarDeteccao(): void
    {
        Parameter::query()->create([
            'key' => 'features.deteccao_abuso',
            'group' => 'features',
            'type' => 'boolean',
            'value' => '1',
            'default_value' => '0',
            'validation_rules' => ['required', 'boolean'],
            'description' => 'Toggle de teste da detecção de abuso.',
        ]);
    }

    public function test_tag_resolve_os_cinco_detectores(): void
    {
        $keys = collect(app()->tagged('abuse.detectors'))
            ->map(fn ($detector) => $detector->key())
            ->all();

        $this->assertEqualsCanonicalizing([
            'volume_cnpj',
            'volume_contador',
            'poligono_repetido',
            'inscricao_atividades_incompativeis',
            'condicionante_evasao',
        ], $keys);
    }

    public function test_pipeline_itera_os_cinco_detectores_e_e_idempotente(): void
    {
        // Padrão estrutural (polígono repetido em 2 endereços) que SÓ um detector
        // da 12-08 captura: prova que o serviço da 12-06 agora itera os novos.
        $this->ligarDeteccao();
        $poligono = $this->poligono(-38.5108, -12.9711);
        $this->solicitacao([
            'property_polygon_geojson' => $poligono,
            'address_street' => 'Rua A', 'address_number' => '1',
            'address_neighborhood' => 'Centro', 'address_zip' => '40000001',
        ]);
        $this->solicitacao([
            'property_polygon_geojson' => $poligono,
            'address_street' => 'Rua B', 'address_number' => '2',
            'address_neighborhood' => 'Comércio', 'address_zip' => '40000002',
        ]);

        $service = app(AbuseDetectionService::class);

        $primeiro = $service->detectar();
        $this->assertTrue($primeiro['executado']);
        $this->assertGreaterThanOrEqual(1, $primeiro['criados']);
        $this->assertSame(1, AbuseAlert::query()->where('rule_key', 'poligono_repetido')->count());

        $segundo = $service->detectar();
        $this->assertSame(0, $segundo['criados']);
        $this->assertSame(1, AbuseAlert::query()->where('rule_key', 'poligono_repetido')->count());
    }
}
