<?php

namespace Tests\Feature\Servicos;

use App\Models\Cnae;
use App\Models\Company;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Sefaz\ConfiguraLegadoSimplifica;
use Tests\TestCase;

/**
 * Preenche a condição dos eventos já gravados. A alteração de atividade sem
 * processo anterior consulta o Simplifica, que aceita 60 requisições por
 * minuto: no limite, o comando espera e tenta de novo em vez de seguir falhando.
 */
class DetectarCondicoesServicosCommandTest extends TestCase
{
    use ConfiguraLegadoSimplifica;
    use LazilyRefreshDatabase;

    private const ATIVIDADES = 'Alteração de atividades econômicas (principal e secundárias)';

    private const ENDERECO = 'Alteração de endereço dentro do mesmo município';

    private const LIMITE = [['mensagem' => 'Limite de 60 requisições por minuto excedido.'], 429];

    protected function setUp(): void
    {
        parent::setUp();

        config(['viabiliza.sefaz.consulta.ativa' => false]);
        $this->configurarLegado();
    }

    /**
     * @param  list<array{0: array<string, mixed>, 1?: int}>  $respostas
     */
    private function legadoResponde(array $respostas): void
    {
        $sequencia = Http::sequence();

        foreach ($respostas as $resposta) {
            $sequencia->push(...$resposta);
        }

        Http::fake([
            'legado.test/v1/tvls?*' => $sequencia,
            'legado.test/v1/tvls/416389' => Http::response(['data' => [
                'numero' => 416389, 'tipo' => 'provisoria', 'deferida' => true, 'ativa' => true, 'validade' => '2027-01-01T00:00:00Z',
                'atividades' => [['codigo' => '4711-3/02', 'descricao' => 'Comércio']],
            ]]),
        ]);
    }

    private function eventoDeAtividade(): ViabilityRequestEvento
    {
        $processo = ViabilityRequest::factory()->create([
            'company_id' => Company::factory()->create(['cnpj' => '12345678000190'])->id,
        ]);

        foreach (['4711302', '5611201'] as $indice => $codigo) {
            $cnae = Cnae::query()->firstOrCreate(['code' => $codigo], Cnae::factory()->make(['code' => $codigo])->toArray());
            $processo->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        return ViabilityRequestEvento::factory()->create(['viability_request_id' => $processo->id, 'codigo' => '244', 'nome' => self::ATIVIDADES, 'condicao' => null]);
    }

    private function eventoDeEndereco(): ViabilityRequestEvento
    {
        $empresa = Company::factory()->create();
        $endereco = [
            'company_id' => $empresa->id,
            'cod_log' => '1234',
            'address_street' => 'Rua A',
            'address_number' => '10',
            'address_neighborhood' => 'Centro',
            'address_complement' => 'Sala 1',
            'address_zip' => '40000000',
        ];
        ViabilityRequest::factory()->create([...$endereco, 'used_area_m2' => 50]);
        $atual = ViabilityRequest::factory()->create([...$endereco, 'used_area_m2' => 80]);

        return ViabilityRequestEvento::factory()->create(['viability_request_id' => $atual->id, 'codigo' => '300', 'nome' => self::ENDERECO, 'condicao' => null]);
    }

    public function test_limite_do_simplifica_espera_e_tenta_de_novo(): void
    {
        $this->legadoResponde([
            self::LIMITE,
            [['data' => [['numero' => 416389, 'tipo' => 'provisoria', 'deferida' => true, 'ativa' => true, 'validade' => '2027-01-01T00:00:00Z']]]],
        ]);
        $evento = $this->eventoDeAtividade();

        $this->artisan('servicos:detectar-condicoes', ['--espera' => 0])->assertSuccessful();

        $this->assertSame('cnae_incluido', $evento->fresh()->condicao);
    }

    public function test_simplifica_sem_resposta_deixa_o_evento_sem_condicao_e_avisa(): void
    {
        $this->legadoResponde(array_fill(0, 5, self::LIMITE));
        $evento = $this->eventoDeAtividade();

        $this->artisan('servicos:detectar-condicoes', ['--espera' => 0, '--tentativas' => 2])
            ->expectsOutputToContain('Simplifica sem resposta: 1')
            ->assertFailed();

        $this->assertNull($evento->fresh()->condicao);
        Http::assertSentCount(2);
    }

    public function test_so_endereco_nao_consulta_o_simplifica(): void
    {
        Http::fake();
        $atividade = $this->eventoDeAtividade();
        $endereco = $this->eventoDeEndereco();

        $this->artisan('servicos:detectar-condicoes', ['--so-endereco' => true])->assertSuccessful();

        $this->assertSame('endereco_espaco', $endereco->fresh()->condicao);
        $this->assertNull($atividade->fresh()->condicao);
        Http::assertNothingSent();
    }
}
