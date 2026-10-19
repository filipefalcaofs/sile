<?php

namespace App\Services\Renovacao;

use App\Models\Company;
use App\Services\Legado\LegadoViabilidadeClient;
use App\Services\Legado\TvlLegadaDetalhe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Consulta as provisórias do sistema legado para o portal e as avalia para
 * renovação. TVL de outro titular nunca é devolvida, para não revelar sua
 * existência; falha do cliente ou payload malformado vira indisponível.
 */
class ProvisoriasLegadasService
{
    public function __construct(
        private readonly LegadoViabilidadeClient $client,
        private readonly RenovacaoLegadaElegibilidadeService $elegibilidade,
    ) {}

    /**
     * @param  Collection<int, Company>  $empresas
     * @return array{itens: list<array<string, mixed>>, indisponivel: bool}
     */
    public function paraEmpresas(Collection $empresas): array
    {
        $itens = [];
        $indisponivel = false;

        foreach ($empresas as $empresa) {
            try {
                $detalhes = [];
                $cnpj = preg_replace('/\D/', '', (string) $empresa->cnpj) ?? '';

                foreach ($this->client->provisoriasPorCnpj($cnpj) as $resumo) {
                    $detalhe = $this->client->tvlPorNumero($resumo->numero);

                    if ($detalhe !== null) {
                        $detalhes[] = $detalhe;
                    }
                }
            } catch (\Throwable $e) {
                $this->registrarFalha($e);
                $indisponivel = true;

                continue;
            }

            foreach ($detalhes as $detalhe) {
                $item = $this->item($detalhe, $empresa);

                if ($item !== null) {
                    $itens[$item['numero']] ??= $item;
                }
            }
        }

        return ['itens' => array_values($itens), 'indisponivel' => $indisponivel];
    }

    /**
     * @return array{itens: list<array<string, mixed>>, indisponivel: bool}
     */
    public function buscarPorNumero(Company $empresa, string $numeroViabilidade): array
    {
        $numero = preg_replace('/\s+/', '', trim($numeroViabilidade)) ?? '';
        $detalhes = [];

        try {
            if (preg_match('/^\d{7}$/', $numero) === 1) {
                $detalhe = $this->client->tvlPorNumero((int) $numero);
                $detalhes = $detalhe === null ? [] : [$detalhe];
            } elseif (preg_match('/^\d{10}-\d{8}\/\d{4}$/', $numero) === 1) {
                foreach ($this->client->tvlsDoProcesso($numero) as $resumo) {
                    $detalhe = $this->client->tvlPorNumero($resumo->numero);

                    if ($detalhe !== null) {
                        $detalhes[] = $detalhe;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->registrarFalha($e);

            return ['itens' => [], 'indisponivel' => true];
        }

        $itens = [];

        foreach ($detalhes as $detalhe) {
            $item = $this->item($detalhe, $empresa);

            if ($item !== null) {
                $itens[$item['numero']] ??= $item;
            }
        }

        return ['itens' => array_values($itens), 'indisponivel' => false];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function item(TvlLegadaDetalhe $tvl, Company $empresa): ?array
    {
        if ($this->elegibilidade->titularidade($tvl, $empresa) !== null) {
            return null;
        }

        $veredito = $this->elegibilidade->avaliar($tvl, $empresa);

        return [
            'numero' => $tvl->numero,
            'empresa_id' => $empresa->id,
            'empresa' => $empresa->legal_name,
            'validade' => $tvl->validade?->format('Y-m-d'),
            'situacao' => $tvl->situacao,
            'dias_para_vencer' => $tvl->diasParaVencer,
            'endereco' => $this->endereco($tvl),
            'elegivel' => $veredito->elegivel,
            'motivo' => $veredito->motivo,
            'origem' => 'legado',
        ];
    }

    private function endereco(TvlLegadaDetalhe $tvl): string
    {
        $partes = [
            $tvl->endereco['logradouro'],
            $tvl->endereco['numero'],
            $tvl->endereco['bairro'],
            $tvl->endereco['cep'],
        ];

        return implode(', ', array_filter($partes, fn (?string $parte): bool => $parte !== null && $parte !== ''));
    }

    private function registrarFalha(\Throwable $e): void
    {
        Log::warning('Consulta de provisórias legadas indisponível.', ['erro' => $e::class]);
    }
}
