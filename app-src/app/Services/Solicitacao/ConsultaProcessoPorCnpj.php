<?php

namespace App\Services\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Legado\LegadoIndisponivelException;
use App\Services\Legado\LegadoViabilidadeClient;
use Carbon\CarbonImmutable;

/**
 * Processos de uma empresa pelo CNPJ, para a consulta de processo do portal:
 * os do Viabiliza (sem rascunho) e os do sistema anterior (Simplifica). Cada
 * item leva à consulta do número; nenhum dado pessoal sai daqui.
 */
class ConsultaProcessoPorCnpj
{
    private const LIMITE = 50;

    public function __construct(private readonly LegadoViabilidadeClient $legado) {}

    public static function cnpj(?string $termo): ?string
    {
        $termo = trim((string) $termo);
        $digitos = preg_replace('/\D/', '', $termo) ?? '';

        return strlen($digitos) === 14 && preg_match('/^[\d.\/\s-]+$/', $termo) === 1 ? $digitos : null;
    }

    /**
     * @return array{lista: list<array{origem: string, numero: string, empresa: ?string, situacao: string, data: ?string}>, legado_indisponivel: bool}
     */
    public function listar(string $cnpj): array
    {
        $viabiliza = ViabilityRequest::query()
            ->where('status', '!=', ViabilityRequestStatus::Rascunho)
            ->whereNotNull('protocol_number')
            ->whereHas('company', fn ($empresa) => $empresa->where('cnpj', $cnpj))
            ->with('company')
            ->orderByDesc('protocoled_at')
            ->orderByDesc('id')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (ViabilityRequest $processo): array => [
                'origem' => 'viabiliza',
                'numero' => (string) $processo->protocol_number,
                'empresa' => $processo->company?->legal_name,
                'situacao' => $processo->status->publicLabel(),
                'data' => $processo->protocoled_at?->toIso8601String(),
            ])
            ->all();

        $empresa = $viabiliza[0]['empresa'] ?? null;
        $legado = [];
        $indisponivel = false;

        try {
            foreach (array_slice($this->legado->processosPorDocumento($cnpj), 0, self::LIMITE) as $item) {
                $legado[] = [
                    'origem' => 'legado',
                    'numero' => (string) ($item['numero'] ?? ''),
                    'empresa' => $empresa,
                    'situacao' => (string) ($item['status']['nome'] ?? 'Situação não informada'),
                    'data' => $this->data($item['criado_em'] ?? null),
                ];
            }
        } catch (LegadoIndisponivelException) {
            $indisponivel = true;
        }

        return [
            'lista' => array_values(array_filter([...$viabiliza, ...$legado], fn (array $item): bool => $item['numero'] !== '')),
            'legado_indisponivel' => $indisponivel,
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
