<?php

namespace App\Services\Legado;

use Carbon\CarbonImmutable;

/**
 * Consulta de processos do Simplifica para a tela de processos da gestão.
 * Escolhe a chave da API pelo que foi informado: os campos específicos do
 * filtro avançado valem mais que a busca livre. Nada é gravado aqui.
 */
class BuscaProcessosLegado
{
    public function __construct(private readonly LegadoViabilidadeClient $client) {}

    /**
     * @param  array<string, string>  $filtros
     * @return array{consultado: bool, processos: list<array<string, mixed>>}
     *
     * @throws LegadoIndisponivelException
     */
    public function buscar(array $filtros): array
    {
        $chave = $this->chave($filtros);

        if ($chave === null) {
            return ['consultado' => false, 'processos' => []];
        }

        $processos = array_map(
            fn (array $processo): array => $this->formatar($processo),
            $this->client->processosPorChave($chave[0], $chave[1]),
        );

        return ['consultado' => true, 'processos' => $processos];
    }

    /**
     * Ficha completa de um processo do Simplifica; null quando o número não
     * tem o formato esperado ou o processo não existe.
     *
     * @return array<string, mixed>|null
     *
     * @throws LegadoIndisponivelException
     */
    public function detalhe(string $numero): ?array
    {
        $numero = trim($numero);

        if (! self::ehNumeroDeProcesso($numero)) {
            return null;
        }

        $dados = $this->client->processo($numero);

        return $dados === null ? null : $this->formatarDetalhe($dados);
    }

    /**
     * @param  array<string, string>  $filtros
     * @return array{0: string, 1: string}|null
     */
    private function chave(array $filtros): ?array
    {
        $cnpj = $this->digitos($filtros['cnpj'] ?? '');
        $inscricao = $this->digitos($filtros['inscricao'] ?? '');
        $tvl = $this->digitos($filtros['produto_tvl'] ?? '');
        $protocolo = trim($filtros['protocolo'] ?? '');

        return match (true) {
            in_array(strlen($cnpj), [11, 14], true) => ['documento', $cnpj],
            preg_match('/^\d{1,12}$/', $inscricao) === 1 => ['inscricao', $inscricao],
            preg_match('/^\d{1,18}$/', $tvl) === 1 => ['numero_tvl', $tvl],
            self::ehNumeroDeProcesso($protocolo) => ['numero_processo', $protocolo],
            default => $this->chaveDaBusca(trim($filtros['busca'] ?? '')),
        };
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function chaveDaBusca(string $busca): ?array
    {
        if (self::ehNumeroDeProcesso($busca)) {
            return ['numero_processo', $busca];
        }

        if (preg_match('/^[\d.\-\/\s]+$/', $busca) !== 1) {
            return null;
        }

        $digitos = $this->digitos($busca);

        return match (true) {
            in_array(strlen($digitos), [11, 14], true) => ['documento', $digitos],
            strlen($digitos) === 7 => ['numero_tvl', $digitos],
            preg_match('/^\d{1,12}$/', $digitos) === 1 => ['inscricao', $digitos],
            default => null,
        };
    }

    /**
     * Número interno (0000000000-00000000/0000) ou protocolo REGIN (BAP2601568762).
     */
    public static function ehNumeroDeProcesso(string $valor): bool
    {
        return preg_match('/^\d{10}-\d{8}\/\d{4}$/', $valor) === 1
            || preg_match('/^[A-Za-z]{3}\d{10}$/', $valor) === 1;
    }

    private function digitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function formatarDetalhe(array $dados): array
    {
        $resumo = $this->formatar($dados);
        $solicitante = is_array($dados['solicitante'] ?? null) ? $dados['solicitante'] : [];
        $localizacao = is_array($dados['localizacao'] ?? null) ? $dados['localizacao'] : null;
        $parecer = is_array($dados['parecer'] ?? null) ? $dados['parecer'] : null;

        return [
            'numero' => $resumo['numero'],
            'protocolo' => $resumo['protocolo'],
            'criado_em' => $resumo['criado_em'],
            'situacao' => $resumo['situacao'],
            'finalizado' => $resumo['finalizado'],
            'solicitante' => [
                'nome' => $solicitante['nome'] ?? null,
                'documento' => $solicitante['documento'] ?? null,
            ],
            'localizacao' => $localizacao === null ? null : [
                'latitude' => $localizacao['latitude'] ?? null,
                'longitude' => $localizacao['longitude'] ?? null,
            ],
            'parecer' => $parecer === null ? null : [
                'deferido' => $parecer['deferido'] ?? null,
                'texto' => $parecer['texto'] ?? null,
                'tipo' => $parecer['tipo'] ?? null,
                'atualizado_em' => $this->data($parecer['atualizado_em'] ?? null),
                'valido_ate' => $this->data($parecer['valido_ate'] ?? null),
            ],
            'fichas' => array_values(array_map(fn (array $ficha): array => [
                'atualizado_em' => $this->data($ficha['atualizado_em'] ?? null),
                'finalizada' => $ficha['finalizada'] ?? null,
                'deferido' => $ficha['deferido'] ?? null,
                'parecer' => $ficha['parecer'] ?? null,
                'numero_tvl' => $ficha['numero_tvl'] ?? null,
                'condicionante' => $ficha['condicionante'] ?? null,
                'itens' => $this->lista($ficha['itens'] ?? null, ['campo', 'deferido', 'em_analise']),
                'atividades' => $this->lista($ficha['atividades'] ?? null, [
                    'codigo', 'descricao', 'deferido', 'em_analise', 'incluida', 'excluida', 'enquadrado', 'enquadramento',
                ]),
            ], $this->itens($dados['fichas'] ?? null))),
            'tvls' => array_values(array_map(fn (array $tvl): array => [
                'numero' => $tvl['numero'] ?? null,
                'tipo' => $tvl['tipo'] ?? null,
                'deferida' => $tvl['deferida'] ?? null,
                'ativa' => $tvl['ativa'] ?? null,
                'emissao' => $this->data($tvl['emissao'] ?? null),
                'validade' => $this->data($tvl['validade'] ?? null),
                'situacao' => $tvl['situacao'] ?? null,
                'razao_social' => $tvl['razao_social'] ?? null,
                'inscricao_imobiliaria' => $tvl['inscricao_imobiliaria'] ?? null,
                'endereco' => is_array($tvl['endereco'] ?? null) ? $tvl['endereco'] : null,
                'escritorio_virtual' => is_array($tvl['escritorio_virtual'] ?? null) ? $tvl['escritorio_virtual'] : null,
            ], $this->itens($dados['tvls'] ?? null))),
            'historico' => array_values(array_map(fn (array $item): array => [
                'em' => $this->data($item['em'] ?? null),
                'titulo' => $item['titulo'] ?? null,
                'mensagem' => $item['mensagem'] ?? null,
                'status' => $item['status'] ?? null,
                'por' => $item['por'] ?? null,
            ], $this->itens($dados['historico'] ?? null))),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itens(mixed $valor): array
    {
        return is_array($valor) ? array_values(array_filter($valor, 'is_array')) : [];
    }

    /**
     * @param  list<string>  $campos
     * @return list<array<string, mixed>>
     */
    private function lista(mixed $valor, array $campos): array
    {
        return array_map(
            fn (array $item): array => array_combine($campos, array_map(fn (string $campo): mixed => $item[$campo] ?? null, $campos)),
            $this->itens($valor),
        );
    }

    /**
     * @param  array<string, mixed>  $processo
     * @return array<string, mixed>
     */
    private function formatar(array $processo): array
    {
        $status = is_array($processo['status'] ?? null) ? $processo['status'] : [];
        $nome = is_string($status['nome'] ?? null) && $status['nome'] !== '' ? $status['nome'] : 'Situação não informada';

        return [
            'numero' => (string) ($processo['numero'] ?? ''),
            'protocolo' => is_string($processo['protocolo'] ?? null) ? $processo['protocolo'] : null,
            'criado_em' => $this->data($processo['criado_em'] ?? null),
            'situacao' => $nome,
            'finalizado' => ($status['fim'] ?? false) === true,
            'tvls' => array_values(array_map('intval', is_array($processo['numeros_tvl'] ?? null) ? $processo['numeros_tvl'] : [])),
        ];
    }

    private function data(mixed $valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($valor)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
