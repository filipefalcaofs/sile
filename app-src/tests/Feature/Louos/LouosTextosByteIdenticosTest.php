<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\LouosEnquadramentoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Golden de byte-identidade dos textos decisórios do motor LOUOS (Fase 4,
 * Task 2): congela as strings EXATAS emitidas hoje para a matriz de cenários
 * (permitido, permitido_com_condicoes, nao_permitido, sem enquadramento, zona
 * pendente/sem regra, quadro10 sem versão, via pendente/sem atributo/sem
 * versão/sem regra). Escrito ANTES da migração das constantes/templates para o
 * catálogo `decision_texts` — verde no código não migrado prova que o golden
 * está correto; verde depois prova que a migração não mudou uma vírgula.
 */
class LouosTextosByteIdenticosTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private function service(): LouosEnquadramentoService
    {
        return app(LouosEnquadramentoService::class);
    }

    private function enquadramentoFaixa(
        string $cnae,
        string $grupo,
        string $subgrupo,
        float $areaMin = 0,
        ?float $areaMax = null,
    ): void {
        $this->seedTratamentoPlanilha();
    }

    private function enquadramentoSemFaixa(): void
    {
        $this->seedTratamentoPlanilha();
    }

    private function quadro10Permissao(string $zona, string $grupoUso, Quadro10Permissao $permissao): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $version->id,
            'zona' => $zona,
            'grupo_uso' => $grupoUso,
            'subgrupo' => '',
            'permissao' => $permissao,
            'condicionante_ref' => null,
            'base_legal' => null,
        ]);
    }

    /**
     * Versão vigente do Quadro 10 SEM linha para a combinação pedida — força
     * o nao_encontrado por ausência de regra (não por ausência de versão).
     */
    private function quadro10SemRegraParaACombinacao(): void
    {
        RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'lei-9148-2016-quadro10',
            'rules_version' => 'lei-9148-2016-quadro10',
        ]);
    }

    /**
     * @param  array<string, mixed>  $condicoes
     */
    private function quadro11aCondicao(string $classeVia, string $grupoUso, array $condicoes): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro11a)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro11a,
                'version' => 'lei-9148-2016-quadro11a',
                'rules_version' => 'lei-9148-2016-quadro11a',
            ]);

        LouosQuadro11CondicaoVia::factory()->create([
            'rule_version_id' => $version->id,
            'classe_via' => $classeVia,
            'grupo_uso' => $grupoUso,
            'condicoes' => $condicoes,
        ]);
    }

    private function quadro11aSemRegraParaACombinacao(): void
    {
        RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11a,
            'version' => 'lei-9148-2016-quadro11a',
            'rules_version' => 'lei-9148-2016-quadro11a',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dimVazia(): array
    {
        return [
            'status' => 'nao_encontrado',
            'nome' => null,
            'propriedades' => null,
            'motivo' => null,
            'versao_camada' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function zonaIdentificada(string $nome): array
    {
        return [
            'status' => 'identificado',
            'nome' => $nome,
            'propriedades' => ['NOME' => $nome],
            'motivo' => null,
            'versao_camada' => 'zoneamento-louos-2026',
        ];
    }

    /**
     * @param  array<string, mixed>  $propriedades
     * @return array<string, mixed>
     */
    private function viaIdentificada(array $propriedades): array
    {
        return [
            'status' => 'identificado',
            'nome' => 'Rua das Laranjeiras',
            'propriedades' => $propriedades,
            'motivo' => null,
            'versao_camada' => 'eixo-viario-2026',
            'distancia_m' => 4.2,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $zona
     * @param  array<string, mixed>|null  $via
     */
    private function territorio(?array $zona = null, ?array $via = null): TerritoryResult
    {
        return new TerritoryResult(
            bairro: $this->dimVazia(),
            via: $via ?? $this->dimVazia() + ['distancia_m' => null],
            zona: $zona ?? $this->dimVazia(),
            lote: $this->dimVazia(),
            restricoes: ['status' => 'nao_encontrado', 'itens' => [], 'motivo' => null, 'versao_camada' => null],
        );
    }

    public function test_permitido_emite_os_textos_exatos(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');
        $this->quadro10Permissao('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio(zona: $this->zonaIdentificada('ZR-1')),
            [11 => true],
        ));

        $this->assertSame(ResultadoViabilidade::Permitido->value, $result->resultado());
        $this->assertSame(
            'O CNAE 4712-1/00 com área 100 m² enquadra-se no grupo nR1 (nR1-01) da LOUOS (07.01.05). O enquadramento não autoriza o uso na zona — só define o grupo.',
            $result->enquadramento['motivo'],
        );
        $this->assertSame(
            'O grupo nR1 é permitido na zona ZR-1 segundo o Quadro 10 da LOUOS — é este quadro que permite ou proíbe o uso no território.',
            $result->quadro10['motivo'],
        );
        $this->assertSame(
            'Condições pela via dependem da classificação viária LOUOS (pendente SEDUR)',
            $result->quadro11a['motivo'],
        );
        $this->assertSame(
            'Permitido: o CNAE 4712-1/00 (área 100 m²) classificou-se no grupo nR1 pelo enquadramento da planilha vigente e esse grupo é permitido na zona ZR-1 pelo Quadro 10. Atividade permitida na zona, sem condicionantes incidentes.',
            $result->consolidado['motivo'],
        );
        $this->assertSame(
            ['Lei nº 9.148/2016 (LOUOS) — nR1-01', 'Lei nº 9.148/2016 (LOUOS) — Quadro 10'],
            $result->consolidado['fundamentacao'],
        );
    }

    public function test_permitido_com_condicoes_emite_os_textos_exatos(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');
        $this->quadro10Permissao('ZR-1', 'nR1', Quadro10Permissao::PermitidoCondicionado);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio(zona: $this->zonaIdentificada('ZR-1')),
            [11 => true],
        ));

        $this->assertSame(ResultadoViabilidade::PermitidoComCondicoes->value, $result->resultado());
        $this->assertSame(
            'O grupo nR1 é permitido condicionado na zona ZR-1 segundo o Quadro 10 da LOUOS — é este quadro que permite ou proíbe o uso no território.',
            $result->quadro10['motivo'],
        );
        $this->assertSame(
            'Permitido: o CNAE 4712-1/00 (área 100 m²) classificou-se no grupo nR1 pelo enquadramento da planilha vigente e esse grupo é permitido na zona ZR-1 pelo Quadro 10. Atividade permitida na zona mediante observância das condicionantes.',
            $result->consolidado['motivo'],
        );
    }

    public function test_nao_permitido_emite_os_textos_exatos(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');
        $this->quadro10Permissao('ZPAM', 'nR1', Quadro10Permissao::Proibido);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio(zona: $this->zonaIdentificada('ZPAM')),
            [11 => true],
        ));

        $this->assertSame(ResultadoViabilidade::NaoPermitido->value, $result->resultado());
        $this->assertSame(
            'O grupo nR1 é proibido na zona ZPAM segundo o Quadro 10 da LOUOS — é este quadro que permite ou proíbe o uso no território.',
            $result->quadro10['motivo'],
        );
        $this->assertSame(
            'Não permitido: o CNAE 4712-1/00 (área 100 m²) classificou-se no grupo nR1 pelo enquadramento da planilha vigente e esse grupo é proibido na zona ZPAM pelo Quadro 10. Atividade proibida na zona pelo Quadro 10.',
            $result->consolidado['motivo'],
        );
        $this->assertSame(
            ['Lei nº 9.148/2016 (LOUOS) — nR1-01', 'Lei nº 9.148/2016 (LOUOS) — Quadro 10'],
            $result->consolidado['fundamentacao'],
        );
    }

    public function test_sem_enquadramento_emite_os_textos_exatos(): void
    {
        $this->enquadramentoSemFaixa();
        $this->quadro10Permissao('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '9999-9/99',
            $this->territorio(zona: $this->zonaIdentificada('ZR-1')),
            [11 => true],
        ));

        $this->assertSame(ResultadoViabilidade::Pendente->value, $result->resultado());
        $this->assertSame(
            'CNAE sem regra de tratamento vigente',
            $result->enquadramento['motivo'],
        );
        $this->assertSame(
            'Sem enquadramento de uso não há permissão a verificar',
            $result->quadro10['motivo'],
        );
        $this->assertSame(
            'Atividade sem enquadramento na planilha vigente — segue para análise técnica',
            $result->consolidado['motivo'],
        );
        $this->assertSame(
            ['Atividade sem enquadramento na planilha vigente — segue para análise técnica'],
            $result->consolidado['fundamentacao'],
        );
    }

    public function test_zona_pendente_emite_os_textos_exatos(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');
        $this->quadro10Permissao('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        // Consulta sem território: não há zona a avaliar — degradação honesta.
        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(100, '4712-1/00', null, [11 => true]));

        $this->assertSame(ResultadoViabilidade::Pendente->value, $result->resultado());
        $this->assertSame(
            'Permissão por zona pendente da base oficial (SEDUR)',
            $result->quadro10['motivo'],
        );
        $this->assertSame(
            'Permissão por zona pendente da base oficial (SEDUR)',
            $result->consolidado['motivo'],
        );
        $this->assertSame(
            ['Lei nº 9.148/2016 (LOUOS) — nR1-01', 'Permissão por zona pendente da base oficial (SEDUR)'],
            $result->consolidado['fundamentacao'],
        );
    }

    public function test_zona_sem_regra_emite_os_textos_exatos(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');
        $this->quadro10SemRegraParaACombinacao();

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio(zona: $this->zonaIdentificada('ZR-1')),
            [11 => true],
        ));

        $this->assertSame(ResultadoViabilidade::Pendente->value, $result->resultado());
        $this->assertSame(
            'Combinação zona × grupo de uso sem regra no Quadro 10 vigente',
            $result->quadro10['motivo'],
        );
        $this->assertSame(
            'Combinação zona × grupo de uso sem regra no Quadro 10 vigente',
            $result->consolidado['motivo'],
        );
    }

    public function test_quadro10_sem_versao_emite_os_textos_exatos(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio(zona: $this->zonaIdentificada('ZR-1')),
            [11 => true],
        ));

        $this->assertSame(ResultadoViabilidade::Pendente->value, $result->resultado());
        $this->assertSame('Quadro 10 sem versão vigente', $result->quadro10['motivo']);
        $this->assertSame('Quadro 10 sem versão vigente', $result->consolidado['motivo']);
        $this->assertSame(
            ['Lei nº 9.148/2016 (LOUOS) — nR1-01', 'Quadro 10 sem versão vigente'],
            $result->consolidado['fundamentacao'],
        );
    }

    public function test_via_sem_atributo_emite_o_texto_exato(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio(via: $this->viaIdentificada(['NOME_LOGRADOURO' => 'Rua das Laranjeiras'])),
            [11 => true],
        ));

        $this->assertSame(
            'Via identificada, porém sem o atributo de classificação viária LOUOS (pendente SEDUR)',
            $result->quadro11a['motivo'],
        );
    }

    public function test_via_sem_versao_emite_o_texto_exato(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio(via: $this->viaIdentificada(['CLASSE_VIA_LOUOS' => 'via_local'])),
            [11 => true],
        ));

        $this->assertSame(
            'Quadro de condições pela via sem versão vigente',
            $result->quadro11a['motivo'],
        );
    }

    public function test_via_sem_regra_emite_o_texto_exato(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');
        $this->quadro11aSemRegraParaACombinacao();

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio(via: $this->viaIdentificada(['CLASSE_VIA_LOUOS' => 'via_local'])),
            [11 => true],
        ));

        $this->assertSame(
            'Classe viária × grupo de uso sem regra no quadro de via vigente',
            $result->quadro11a['motivo'],
        );
    }

    public function test_via_identificada_emite_o_template_exato_do_quadro_via(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');
        $this->quadro11aCondicao('via_local', 'nR1', ['recuo_frontal_m' => 3]);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio(via: $this->viaIdentificada(['CLASSE_VIA_LOUOS' => 'via_local'])),
            [11 => true],
        ));

        $this->assertSame(
            'O grupo nR1 na classe viária via_local tem condições de instalação pelo Quadro 11-A da LOUOS. O Quadro 11-A pode vedar o uso (Não), encaminhar à CNLU (R) ou condicionar a instalação pela via.',
            $result->quadro11a['motivo'],
        );
    }

    public function test_enquadramento_com_area_decimal(): void
    {
        $this->enquadramentoFaixa('4712100', 'nR1', 'nR1-01');

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(100.5, '4712-1/00', null, [11 => true]));

        $this->assertSame(
            'O CNAE 4712-1/00 com área 100,50 m² enquadra-se no grupo nR1 (nR1-01) da LOUOS (07.01.05). O enquadramento não autoriza o uso na zona — só define o grupo.',
            $result->enquadramento['motivo'],
        );
    }
}
