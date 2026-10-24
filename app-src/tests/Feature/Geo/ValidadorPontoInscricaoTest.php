<?php

namespace Tests\Feature\Geo;

use App\Enums\ConfirmacaoPonto;
use App\Enums\PropertyLocationSource;
use App\Services\Geo\DistanciaGeografica;
use App\Services\Geo\EixoViarioHit;
use App\Services\Geo\GeoServerEixoViarioClient;
use App\Services\Geo\PontoAlternativo;
use App\Services\Geo\PontoAlternativoResolver;
use App\Services\Geo\ValidadorPontoInscricao;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ValidadorPontoInscricaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Eixo leste-oeste da Av. Heitor Dias (fictício, em lat -12.9700). */
    private const EIXO = [[[-38.50, -12.9700], [-38.49, -12.9700]]];

    private const METRO_EM_GRAUS = 1 / 111_320;

    private function validador(EixoViarioHit $eixo, array $candidatos = [], bool $esperaBuscarAlternativos = true): ValidadorPontoInscricao
    {
        $this->mock(GeoServerEixoViarioClient::class, function ($mock) use ($eixo): void {
            $mock->shouldReceive('consultar')->andReturn($eixo)->byDefault();
        });

        $this->mock(PontoAlternativoResolver::class, function ($mock) use ($candidatos, $esperaBuscarAlternativos): void {
            if ($esperaBuscarAlternativos) {
                $mock->shouldReceive('candidatos')->andReturn($candidatos)->byDefault();
            } else {
                $mock->shouldNotReceive('candidatos');
            }
        });

        return app(ValidadorPontoInscricao::class);
    }

    private function eixoHeitorDias(): EixoViarioHit
    {
        return new EixoViarioHit(EixoViarioHit::IDENTIFICADO, self::EIXO);
    }

    private function validar(ValidadorPontoInscricao $validador, float $lat, float $lng, ?string $codLog = '2075')
    {
        return $validador->validar($lat, $lng, $codLog, '98', 'Avenida Heitor Dias, Salvador', '1078739');
    }

    public function test_ponto_perto_da_rua_e_confirmado_sem_chamar_fontes_alternativas(): void
    {
        $validador = $this->validador($this->eixoHeitorDias(), esperaBuscarAlternativos: false);

        $resultado = $this->validar($validador, -12.9705, -38.495);

        $this->assertSame(ConfirmacaoPonto::Confirmado, $resultado->confirmacao);
        $this->assertSame(-12.9705, $resultado->lat);
        $this->assertSame(PropertyLocationSource::CadastroImobiliario, $resultado->origem);
        $this->assertEqualsWithDelta(55.7, $resultado->distanciaOriginalM, 2.0);
    }

    public function test_lote_de_esquina_com_ponto_a_100m_da_rua_nao_e_trocado(): void
    {
        $validador = $this->validador($this->eixoHeitorDias(), esperaBuscarAlternativos: false);

        $resultado = $this->validar($validador, -12.9700 - 100 * self::METRO_EM_GRAUS, -38.495);

        $this->assertSame(ConfirmacaoPonto::Confirmado, $resultado->confirmacao);
        $this->assertSame(PropertyLocationSource::CadastroImobiliario, $resultado->origem);
    }

    public function test_ponto_exatamente_no_limite_e_confirmado(): void
    {
        $lat = -12.9700 - 150 * self::METRO_EM_GRAUS;
        $limite = DistanciaGeografica::ateLinhas($lat, -38.495, self::EIXO);
        config(['viabiliza.geo.validacao_ponto.distancia_maxima_m' => $limite]);
        $validador = $this->validador($this->eixoHeitorDias(), esperaBuscarAlternativos: false);

        $resultado = $this->validar($validador, $lat, -38.495);

        $this->assertSame(ConfirmacaoPonto::Confirmado, $resultado->confirmacao);
    }

    public function test_ponto_na_barros_reis_com_codlog_da_heitor_dias_e_substituido_pelo_endereco(): void
    {
        $alternativo = new PontoAlternativo(-12.9701, -38.495, PropertyLocationSource::EnderecamentoSedur);
        $validador = $this->validador($this->eixoHeitorDias(), [$alternativo]);

        $resultado = $this->validar($validador, -12.965543, -38.478635);

        $this->assertSame(ConfirmacaoPonto::Substituido, $resultado->confirmacao);
        $this->assertSame(-12.9701, $resultado->lat);
        $this->assertSame(-38.495, $resultado->lng);
        $this->assertSame(PropertyLocationSource::EnderecamentoSedur, $resultado->origem);
        $this->assertGreaterThan(150.0, $resultado->distanciaOriginalM);
        $this->assertLessThanOrEqual(150.0, $resultado->distanciaFinalM);
    }

    public function test_candidato_longe_da_rua_tambem_e_recusado_e_o_proximo_e_tentado(): void
    {
        $longe = new PontoAlternativo(-12.9600, -38.495, PropertyLocationSource::EnderecamentoSedur);
        $perto = new PontoAlternativo(-12.9702, -38.495, PropertyLocationSource::Geocodificacao);
        $validador = $this->validador($this->eixoHeitorDias(), [$longe, $perto]);

        $resultado = $this->validar($validador, -12.965543, -38.478635);

        $this->assertSame(ConfirmacaoPonto::Substituido, $resultado->confirmacao);
        $this->assertSame(PropertyLocationSource::Geocodificacao, $resultado->origem);
    }

    public function test_nenhuma_fonte_confirma_vira_nao_confirmado_com_ponto_da_sefaz(): void
    {
        $longe = new PontoAlternativo(-12.9600, -38.495, PropertyLocationSource::Geocodificacao);
        $validador = $this->validador($this->eixoHeitorDias(), [$longe]);

        $resultado = $this->validar($validador, -12.965543, -38.478635);

        $this->assertSame(ConfirmacaoPonto::NaoConfirmado, $resultado->confirmacao);
        $this->assertSame(-12.965543, $resultado->lat);
        $this->assertSame(-38.478635, $resultado->lng);
        $this->assertSame(PropertyLocationSource::CadastroImobiliario, $resultado->origem);
        $this->assertNull($resultado->distanciaFinalM);
    }

    public function test_sem_nenhuma_fonte_alternativa_tambem_e_nao_confirmado(): void
    {
        $validador = $this->validador($this->eixoHeitorDias(), []);

        $this->assertSame(
            ConfirmacaoPonto::NaoConfirmado,
            $this->validar($validador, -12.965543, -38.478635)->confirmacao,
        );
    }

    public function test_sem_eixo_na_sedur_vira_nao_verificado_e_mantem_o_ponto(): void
    {
        $validador = $this->validador(new EixoViarioHit(EixoViarioHit::NAO_ENCONTRADO), esperaBuscarAlternativos: false);

        $resultado = $this->validar($validador, -12.965543, -38.478635);

        $this->assertSame(ConfirmacaoPonto::NaoVerificado, $resultado->confirmacao);
        $this->assertSame(-12.965543, $resultado->lat);
        $this->assertNull($resultado->distanciaOriginalM);
    }

    public function test_sem_codlog_vira_nao_verificado_sem_consultar_o_eixo(): void
    {
        $this->mock(GeoServerEixoViarioClient::class, fn ($mock) => $mock->shouldNotReceive('consultar'));
        $this->mock(PontoAlternativoResolver::class, fn ($mock) => $mock->shouldNotReceive('candidatos'));

        $resultado = $this->validar(app(ValidadorPontoInscricao::class), -12.965543, -38.478635, null);

        $this->assertSame(ConfirmacaoPonto::NaoVerificado, $resultado->confirmacao);
    }

    public function test_toggle_desligado_mantem_ponto_da_sefaz(): void
    {
        config(['viabiliza.features.validacao_ponto_inscricao' => false]);
        $this->mock(GeoServerEixoViarioClient::class, fn ($mock) => $mock->shouldNotReceive('consultar'));
        $this->mock(PontoAlternativoResolver::class, fn ($mock) => $mock->shouldNotReceive('candidatos'));

        $resultado = $this->validar(app(ValidadorPontoInscricao::class), -12.965543, -38.478635);

        $this->assertSame(ConfirmacaoPonto::NaoVerificado, $resultado->confirmacao);
        $this->assertSame(-12.965543, $resultado->lat);
    }
}
