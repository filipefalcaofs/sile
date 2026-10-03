<?php

namespace Tests\Feature\Benchmark;

use App\Services\Benchmark\RedesimClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cliente dos microdados da REDESIM. A API não é publicada como contrato
 * estável, então toda falha é devolvida como resultado — nunca exceção para o
 * chamador — e fica registrada com o motivo.
 */
class RedesimClientTest extends TestCase
{
    private function client(): RedesimClient
    {
        return app(RedesimClient::class);
    }

    private function ligarIntegracao(): void
    {
        config(['sile.features.benchmark_redesim' => true]);
    }

    public function test_baixa_a_competencia_e_devolve_bytes_e_hash(): void
    {
        $this->ligarIntegracao();

        Http::fake([
            '*' => Http::response('conteudo-xlsx', 200),
        ]);

        $download = $this->client()->baixar('BA', 2026, 8);

        $this->assertTrue($download->sucesso);
        $this->assertSame('conteudo-xlsx', $download->conteudo);
        $this->assertSame(13, $download->bytes);
        $this->assertSame(hash('sha256', 'conteudo-xlsx'), $download->hash);
    }

    public function test_monta_a_url_com_a_uf_no_caminho_e_a_competencia_na_query(): void
    {
        $this->ligarIntegracao();

        Http::fake(['*' => Http::response('x', 200)]);

        $this->client()->baixar('SP', 2019, 3);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/exportar/solicitacoes/SP')
                && str_contains($request->url(), 'ano=2019')
                && str_contains($request->url(), 'mes=3');
        });
    }

    public function test_erro_do_servidor_vira_falha_com_motivo(): void
    {
        $this->ligarIntegracao();

        Http::fake(['*' => Http::response('', 500)]);

        $download = $this->client()->baixar('BA', 2026, 8);

        $this->assertFalse($download->sucesso);
        $this->assertStringContainsString('500', (string) $download->motivo);
        $this->assertNull($download->conteudo);
    }

    public function test_falha_de_conexao_vira_falha_com_motivo(): void
    {
        $this->ligarIntegracao();

        Http::fake(fn () => throw new ConnectionException('tempo esgotado'));

        $download = $this->client()->baixar('BA', 2026, 8);

        $this->assertFalse($download->sucesso);
        $this->assertStringContainsString('tempo esgotado', (string) $download->motivo);
    }

    public function test_resposta_vazia_vira_falha(): void
    {
        $this->ligarIntegracao();

        Http::fake(['*' => Http::response('', 200)]);

        $download = $this->client()->baixar('BA', 2026, 8);

        $this->assertFalse($download->sucesso);
        $this->assertStringContainsString('vazia', (string) $download->motivo);
    }

    public function test_integracao_desligada_nao_chama_o_servico(): void
    {
        Http::fake();

        $download = $this->client()->baixar('BA', 2026, 8);

        $this->assertFalse($download->sucesso);
        $this->assertStringContainsString('desligada', (string) $download->motivo);

        Http::assertNothingSent();
    }

    public function test_registra_a_url_chamada_mesmo_na_falha(): void
    {
        $this->ligarIntegracao();

        Http::fake(['*' => Http::response('', 500)]);

        $download = $this->client()->baixar('BA', 2026, 8);

        $this->assertStringContainsString('/exportar/solicitacoes/BA', $download->url);
    }
}
