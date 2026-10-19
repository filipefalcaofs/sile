<?php

namespace Tests\Feature\Regin;

use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoEnderecamentoService;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * A declaração REGIN não busca o lote pelo ponto dentro do job do recebimento:
 * sem polígono pela chave nem pelo SEMAP, a busca pelo ponto (edificações
 * complementares, IA e endereçamento pelo ponto) e a decisão vão para o job de
 * decisão. O recebimento já está vinculado quando esse job é despachado: se o
 * worker matar algo no meio, a nova tentativa não abre um segundo processo.
 */
class ReginDeclaracaoPoligonoPorPontoTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Cadastro Imobiliário com coordenada: o processo nasce com ponto.
     */
    private function comPontoDoCadastro(): void
    {
        config(['viabiliza.features.cadastro_imobiliario' => true]);
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(latitude: -12.98, longitude: -38.46, inscricao: $inscricao, raw: []);
            }
        });
    }

    public function test_busca_por_ponto_vai_para_o_job_de_decisao_com_o_recebimento_ja_vinculado(): void
    {
        config(['viabiliza.features.geoserver_enderecamento' => true]);
        $this->comPontoDoCadastro();
        $consultasPorPonto = 0;
        $poligono = Mockery::mock(PoligonoEnderecamentoService::class);
        $poligono->shouldReceive('aplicar')->andReturnUsing(
            function (ViabilityRequest $request, bool $consultarPorPonto = false) use (&$consultasPorPonto): bool {
                $consultasPorPonto += $consultarPorPonto ? 1 : 0;

                return false;
            },
        );
        $this->app->instance(PoligonoEnderecamentoService::class, $poligono);

        $this->postJson('/api_integracao/recebe', $this->declaracao($this->cnpjValido()))->assertOk();

        $processo = ViabilityRequest::query()->sole();

        $this->assertSame(0, $consultasPorPonto);
        $this->assertSame($processo->id, ReginRecebimento::query()->where('protocolo', 'BAP-2026-0001')->value('viability_request_id'));
        Queue::assertPushed(DecidirFluxoExpressoJob::class, fn (DecidirFluxoExpressoJob $job): bool => $job->viabilityRequestId === $processo->id);
    }

    public function test_declaracao_sem_busca_pelo_ponto_decide_na_hora_sem_job_na_fila(): void
    {
        config(['viabiliza.features.geoserver_enderecamento' => false]);

        $this->postJson('/api_integracao/recebe', $this->declaracao($this->cnpjValido()))->assertOk();

        Queue::assertNotPushed(DecidirFluxoExpressoJob::class);
    }

    public function test_job_da_declaracao_sai_so_depois_do_lote_oficial_e_com_o_ator(): void
    {
        config(['viabiliza.features.geoserver_enderecamento' => true]);
        $this->comPontoDoCadastro();
        $chamadas = [];
        $poligono = Mockery::mock(PoligonoEnderecamentoService::class);
        $poligono->shouldReceive('aplicar')->andReturnUsing(function () use (&$chamadas): bool {
            $chamadas[] = Queue::pushed(DecidirFluxoExpressoJob::class)->count();

            return false;
        });
        $this->app->instance(PoligonoEnderecamentoService::class, $poligono);

        $this->postJson('/api_integracao/recebe', $this->declaracao($this->cnpjValido()))->assertOk();

        $this->assertSame(0, $chamadas[array_key_last($chamadas)]);
        Queue::assertPushed(DecidirFluxoExpressoJob::class, 1);
        Queue::assertPushed(DecidirFluxoExpressoJob::class, fn (DecidirFluxoExpressoJob $job): bool => $job->atorId !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function declaracao(string $cnpjEscritorio): array
    {
        return [
            'protocolo' => 'BAP-2026-0001',
            'razaoSocial' => 'Padaria do Porto Ltda',
            'cpfSolicitante' => '529.982.247-25',
            'nomeSolicitante' => 'Maria Souza',
            'emailSolicitante' => 'maria.souza@exemplo.com',
            'inscricaoImobiliaria' => '12345678',
            'codlog' => '212',
            'tipoLogradouro' => 'Rua',
            'logradouro' => 'das Flores',
            'numero' => '100',
            'bairro' => 'Comércio',
            'cep' => '40020-000',
            'pontoReferencia' => 'Em frente à praça',
            'municipio' => 'Salvador',
            'uf' => 'BA',
            'area' => '120,5',
            'tipoComplemento' => 'Sala',
            'descricaoComplemento' => '2',
            'categoriaImovel' => 'Comercial',
            'cnaePrincipal' => '4712-1/00',
            'cnaesSecundarios' => ['5611-2/01', '5620104'],
            'perguntas' => [
                [
                    'cnae' => '4712100',
                    'pergunta' => '12',
                    'resposta' => 'Não',
                ],
                [
                    'cnae' => '5611201',
                    'pergunta' => 'Há música ao vivo?',
                    'resposta' => 'Sim',
                ],
            ],
            'tipoImovel' => 'Loja',
            'porteEmpresa' => 'Microempresa',
            'atividadeEmAreaPublica' => 'Sim',
            'escritorioVirtual' => 'Sim',
            'atividadeAbrigadaEmEscritorioVirtual' => 'Sim',
            'cnpjEscritorioVirtual' => $cnpjEscritorio,
            'servico' => 'Consulta de viabilidade',
            'dataAbertura' => '2026-10-03',
        ];
    }

    private function cnpjValido(): string
    {
        $base = '112223330001';
        $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $cnpj = $base;

        foreach ([12, 13] as $position) {
            $slice = array_slice($weights, 13 - $position);
            $sum = 0;

            foreach ($slice as $index => $weight) {
                $sum += (ord($cnpj[$index]) - 48) * $weight;
            }

            $rest = $sum % 11;
            $cnpj .= (string) ($rest < 2 ? 0 : 11 - $rest);
        }

        return $cnpj;
    }
}
