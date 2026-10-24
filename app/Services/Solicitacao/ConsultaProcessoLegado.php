<?php

namespace App\Services\Solicitacao;

use App\Models\Company;
use App\Services\Legado\LegadoIndisponivelException;
use App\Services\Legado\LegadoViabilidadeClient;
use Carbon\CarbonImmutable;

/**
 * Processo do sistema anterior (Simplifica) para a consulta de processo do
 * portal, no mesmo formato do processo do Viabiliza. Aceita o número do
 * processo do Simplifica ou o de uma TVL dele. Do histórico saem data e
 * status; o texto das mensagens não, porque pode ser dirigido ao requerente.
 */
class ConsultaProcessoLegado
{
    public function __construct(private readonly LegadoViabilidadeClient $client) {}

    public static function ehNumeroDoLegado(string $numero): bool
    {
        return preg_match('/^\d{7}$/', $numero) === 1 || preg_match('/^\d{10}-\d{8}\/\d{4}$/', $numero) === 1;
    }

    /**
     * @return array{processo: array<string, mixed>, timeline: array<string, mixed>}|null
     *
     * @throws LegadoIndisponivelException
     */
    public function localizar(string $numero): ?array
    {
        if (! self::ehNumeroDoLegado($numero)) {
            return null;
        }

        $numeroProcesso = $numero;

        if (preg_match('/^\d{7}$/', $numero) === 1) {
            $tvl = $this->client->tvlPorNumero((int) $numero);
            $numeroProcesso = $tvl?->processoNumero;

            if ($numeroProcesso === null || $numeroProcesso === '') {
                return null;
            }
        }

        $dados = $this->client->processo($numeroProcesso);

        return $dados === null ? null : $this->formatar($dados);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{processo: array<string, mixed>, timeline: array<string, mixed>}
     */
    private function formatar(array $dados): array
    {
        $tvls = array_values(array_filter(is_array($dados['tvls'] ?? null) ? $dados['tvls'] : [], 'is_array'));
        $primeira = $tvls[0] ?? [];
        $documento = preg_replace('/\D/', '', (string) ($primeira['titular']['documento'] ?? '')) ?? '';
        $status = is_array($dados['status'] ?? null) ? $dados['status'] : [];
        $situacao = is_string($status['nome'] ?? null) && $status['nome'] !== '' ? $status['nome'] : 'Situação não informada';
        $razao = is_string($primeira['razao_social'] ?? null) ? $primeira['razao_social'] : null;

        return [
            'processo' => [
                'origem' => 'legado',
                'protocol_number' => (string) ($dados['numero'] ?? ''),
                'numero_viabilidade' => $tvls === [] ? null : implode(', ', array_map(fn (array $tvl): string => (string) ($tvl['numero'] ?? ''), $tvls)),
                'bap' => null,
                'servico' => 'Viabilidade de localização (sistema anterior)',
                'empresa' => $razao === null && strlen($documento) !== 14 ? null : [
                    'razao_social' => $razao,
                    'cnpj' => strlen($documento) === 14 ? (new Company(['cnpj' => $documento]))->formatted_cnpj : null,
                ],
                'protocolado_em' => $this->data($dados['criado_em'] ?? null),
                'status' => ['value' => ($status['fim'] ?? false) === true ? 'legado_finalizado' : 'legado_em_andamento', 'public_label' => $situacao],
                'convite' => ['aberto' => false, 'prazo' => null],
                'pode_responder' => false,
                'solicitacao_id' => null,
            ],
            'timeline' => [
                'status_atual' => ['value' => 'legado', 'public_label' => $situacao],
                'etapas' => array_values(array_map(fn (array $item): array => [
                    'rotulo' => $this->rotulo($item, (string) ($dados['numero'] ?? '')),
                    'data' => $this->data($item['em'] ?? null),
                ], array_filter(is_array($dados['historico'] ?? null) ? $dados['historico'] : [], 'is_array'))),
                'pendencias' => [],
                'prazo_estimado' => null,
            ],
        ];
    }

    /**
     * Rótulo da etapa: o status do item. No Simplifica o título costuma ser o
     * próprio número do processo, que não diz nada como etapa.
     *
     * @param  array<string, mixed>  $item
     */
    private function rotulo(array $item, string $numeroProcesso): string
    {
        foreach (['status', 'titulo'] as $campo) {
            $valor = trim((string) ($item[$campo] ?? ''));

            if ($valor !== '' && $valor !== $numeroProcesso) {
                return $valor;
            }
        }

        return 'Andamento';
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
