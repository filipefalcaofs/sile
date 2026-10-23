<?php

namespace Tests\Feature\Geo;

use App\Enums\ZonaFonte;
use App\Models\ViabilityRequest;
use App\Services\Geo\ZonaMaiorPorcao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use App\Services\Geo\ZonaProcessoService;
use Database\Seeders\DecisionTextSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Geo\FakeZonaMaiorPorcaoResolver;
use Tests\TestCase;

class ZonaProcessoServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const LOTE = ['type' => 'Polygon', 'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.979], [-38.5, -12.98]]]];

    private FakeZonaMaiorPorcaoResolver $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DecisionTextSeeder::class);
        $this->fake = new FakeZonaMaiorPorcaoResolver;
        $this->app->instance(ZonaMaiorPorcaoResolver::class, $this->fake);
    }

    /**
     * @param  array<string, mixed>|null  $enderecamento
     */
    private function processo(?array $enderecamento = self::LOTE, ?string $zonaPonto = 'ZEIS'): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['address_polygon_geojson' => $enderecamento, 'zona_codigo' => $zonaPonto])->save();

        return $request->fresh();
    }

    public function test_ca_zn_01_maior_porcao_grava_zona_e_fonte(): void
    {
        $this->fake->para(self::LOTE, FakeZonaMaiorPorcaoResolver::identificado('ZPR 3', [
            ['zona' => 'ZPR 3', 'area_m2' => 780.0, 'percentual' => 78.0],
            ['zona' => 'ZEIS', 'area_m2' => 220.0, 'percentual' => 22.0],
        ]));
        $request = $this->processo();

        app(ZonaProcessoService::class)->materializar($request);

        $request->refresh();
        $this->assertSame('ZPR 3', $request->zona_codigo);
        $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->zona_fonte);
    }

    public function test_ca_au_01_audita_vencedora_porcoes_e_camadas(): void
    {
        $this->fake->para(self::LOTE, FakeZonaMaiorPorcaoResolver::identificado('ZPR 3'));
        $request = $this->processo();

        app(ZonaProcessoService::class)->materializar($request);

        $auditoria = Activity::query()->where('event', 'zona-maior-porcao')->where('subject_id', $request->id)->sole();
        $this->assertSame('ZPR 3', $auditoria->properties['zona_vencedora']);
        $this->assertSame('ZPR 3', $auditoria->properties['porcoes'][0]['zona']);
        $this->assertSame('louos_teste:VM_TESTE', $auditoria->properties['camadas'][0]['type_name']);
        $this->assertSame('identificado', $auditoria->properties['status']);
    }

    public function test_ca_zn_03_indisponivel_mantem_a_zona_do_ponto_e_registra_o_motivo(): void
    {
        $this->fake->para(self::LOTE, ZonaMaiorPorcao::indisponivel('Camada de zona sem resposta do GeoServer SEDUR: louos_zeis.'));
        $request = $this->processo();

        app(ZonaProcessoService::class)->materializar($request);

        $request->refresh();
        $this->assertSame('ZEIS', $request->zona_codigo);
        $this->assertSame(ZonaFonte::Ponto, $request->zona_fonte);
        $auditoria = Activity::query()->where('event', 'zona-maior-porcao')->where('subject_id', $request->id)->sole();
        $this->assertStringContainsString('louos_zeis', $auditoria->properties['motivo']);
    }

    public function test_ca_zn_04_empate_nao_grava_zona_vencedora(): void
    {
        $this->fake->para(self::LOTE, FakeZonaMaiorPorcaoResolver::empate('ZPR 3', 'ZEIS'));
        $request = $this->processo(zonaPonto: null);

        $resultado = app(ZonaProcessoService::class)->materializar($request);

        $this->assertNull($request->fresh()->zona_codigo);
        $motivo = app(ZonaProcessoService::class)->motivoEmpate($resultado);
        $this->assertStringContainsString('ZPR 3', $motivo);
        $this->assertStringContainsString('ZEIS', $motivo);
    }

    public function test_sem_poligono_do_enderecamento_marca_a_fonte_ponto_sem_consultar(): void
    {
        $request = $this->processo(enderecamento: null);

        $this->assertNull(app(ZonaProcessoService::class)->materializar($request));
        $this->assertSame(ZonaFonte::Ponto, $request->fresh()->zona_fonte);
        $this->assertSame(0, $this->fake->chamadas);
    }

    public function test_degradacao_nao_apaga_a_zona_ja_decidida_por_area(): void
    {
        $this->fake->para(self::LOTE, ZonaMaiorPorcao::indisponivel('GeoServer fora.'));
        $request = $this->processo(zonaPonto: 'ZPR 3');
        $request->forceFill(['zona_fonte' => ZonaFonte::EnderecamentoMaiorPorcao])->save();

        app(ZonaProcessoService::class)->materializar($request);

        $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->fresh()->zona_fonte);
        $this->assertSame('ZPR 3', $request->fresh()->zona_codigo);
    }

    public function test_hu_182_materializa_a_centralidade_linear(): void
    {
        $this->fake->para(self::LOTE, FakeZonaMaiorPorcaoResolver::centralidadeLinear('ZCLMu'));
        $request = $this->processo();

        app(ZonaProcessoService::class)->materializar($request);

        $request->refresh();
        $this->assertSame('ZCLMu', $request->zona_codigo);
        $this->assertSame(ZonaFonte::CentralidadeLinear, $request->zona_fonte);
        $auditoria = Activity::query()->where('event', 'zona-maior-porcao')->where('subject_id', $request->id)->sole();
        $this->assertSame('hu-182-centralidade-linear', $auditoria->rules_version ?? $auditoria->properties['rules_version'] ?? null);
    }

    public function test_hu_182_conflito_linear_nao_grava_zona_vencedora(): void
    {
        $this->fake->para(self::LOTE, FakeZonaMaiorPorcaoResolver::conflitoLinear());
        $request = $this->processo();

        $resultado = app(ZonaProcessoService::class)->materializar($request);

        $request->refresh();
        $this->assertSame(ZonaMaiorPorcao::CONFLITO_LINEAR, $resultado->status);
        $this->assertSame('ZEIS', $request->zona_codigo);
        $this->assertSame(ZonaFonte::Ponto, $request->zona_fonte);
    }

    public function test_hu_182_motivo_do_conflito_cita_as_duas_zonas(): void
    {
        $motivo = app(ZonaProcessoService::class)->motivoConflito(FakeZonaMaiorPorcaoResolver::conflitoLinear());

        $this->assertStringContainsString('ZCLMu', $motivo);
        $this->assertStringContainsString('ZCLMe', $motivo);
    }
}
