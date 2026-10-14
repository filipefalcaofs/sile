<?php

namespace Tests\Feature\Regin;

use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoEnderecamentoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * A declaração REGIN busca o lote pelo ponto (consulta lenta) dentro do job do
 * recebimento. O recebimento já tem de estar vinculado ao processo nessa hora:
 * se o worker matar o job no meio, a nova tentativa não abre um segundo processo.
 */
class ReginDeclaracaoPoligonoPorPontoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_recebimento_ja_esta_vinculado_quando_a_busca_por_ponto_comeca(): void
    {
        $vinculadoNaBusca = [];
        $poligono = Mockery::mock(PoligonoEnderecamentoService::class);
        $poligono->shouldReceive('aplicar')->andReturnUsing(
            function (ViabilityRequest $request, bool $consultarPorPonto = false) use (&$vinculadoNaBusca): bool {
                if ($consultarPorPonto) {
                    $vinculadoNaBusca[] = ReginRecebimento::query()
                        ->where('protocolo', 'BAP-2026-0001')
                        ->value('viability_request_id');
                }

                return false;
            },
        );
        $this->app->instance(PoligonoEnderecamentoService::class, $poligono);

        $this->postJson('/api_integracao/recebe', $this->declaracao($this->cnpjValido()))->assertOk();

        $processo = ViabilityRequest::query()->sole();

        $this->assertSame([$processo->id], $vinculadoNaBusca);
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
