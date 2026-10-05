<?php

namespace Tests\Feature\Benchmark;

use App\Services\Benchmark\RedesimSolicitacaoParser;
use Tests\TestCase;

/**
 * Leitura dos microdados da REDESIM.
 *
 * A fixture é um recorte REAL do arquivo da Bahia de agosto de 2026, baixado
 * de `estatistica.redesim.gov.br` em 2026-10-03: 12 linhas de dados, com as
 * duas linhas de cabeçalho preservadas (a linha 1 é agrupamento, a linha 2
 * traz os nomes dos campos) e uma linha com valor não numérico, que o arquivo
 * original também tem.
 *
 * Dois detalhes do arquivo real que quebram parser ingênuo: os nomes
 * `QTDE.  HH VIABILIDADE END` e `ATIVIDADE  ECONÔMICA PRINCIPAL` têm espaço
 * duplo, e a ordem das colunas muda entre competências.
 */
class RedesimSolicitacaoParserTest extends TestCase
{
    private const FIXTURE = 'tests/Fixtures/Benchmark/redesim-ba-202608.xlsx';

    private function parser(): RedesimSolicitacaoParser
    {
        return app(RedesimSolicitacaoParser::class);
    }

    private function conteudo(): string
    {
        return (string) file_get_contents(base_path(self::FIXTURE));
    }

    /**
     * @return list<array{municipio: string, uf: string, horas: float, cnae: string}>
     */
    private function linhas(): array
    {
        return iterator_to_array($this->parser()->linhas($this->conteudo()), false);
    }

    public function test_le_as_linhas_com_municipio_uf_horas_e_cnae(): void
    {
        $linhas = $this->linhas();

        $this->assertCount(11, $linhas);
        $this->assertSame('SALVADOR', $linhas[0]['municipio']);
        $this->assertSame('BA', $linhas[0]['uf']);
        $this->assertIsFloat($linhas[0]['horas']);
        $this->assertMatchesRegularExpression('/^\d{7}$/', $linhas[0]['cnae']);
    }

    public function test_descarta_linha_sem_valor_numerico_de_viabilidade(): void
    {
        $parser = $this->parser();

        iterator_to_array($parser->linhas($this->conteudo()), false);

        // A fixture tem 12 linhas de dados; a de Camaçari traz "-" no campo.
        $this->assertSame(1, $parser->descartadas());
        $this->assertNotContains('CAMACARI', array_column($this->linhas(), 'municipio'));
    }

    public function test_apura_as_horas_de_salvador_da_fixture(): void
    {
        $horas = array_column(
            array_filter($this->linhas(), static fn (array $l): bool => $l['municipio'] === 'SALVADOR'),
            'horas',
        );

        sort($horas);

        $this->assertSame([4.0, 6.0, 24.0, 44.0, 72.0, 73.0], $horas);
    }

    public function test_reconhece_as_colunas_pelo_cabecalho_e_nao_pela_posicao(): void
    {
        $linhas = $this->linhas();

        $municipios = array_unique(array_column($linhas, 'municipio'));

        sort($municipios);

        $this->assertSame(['BARREIRAS', 'FEIRA DE SANTANA', 'SALVADOR'], $municipios);
    }

    public function test_normaliza_o_nome_do_municipio(): void
    {
        foreach ($this->linhas() as $linha) {
            $this->assertSame(strtoupper($linha['municipio']), $linha['municipio']);
            $this->assertStringNotContainsString('  ', $linha['municipio']);
        }
    }

    public function test_conteudo_invalido_nao_produz_linhas(): void
    {
        $linhas = iterator_to_array($this->parser()->linhas('isto não é um xlsx'), false);

        $this->assertSame([], $linhas);
    }
}
