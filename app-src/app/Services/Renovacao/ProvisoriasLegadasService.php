<?php

namespace App\Services\Renovacao;

use App\Models\Company;
use App\Services\Legado\LegadoIndisponivelException;
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
            $cnpj = preg_replace('/\D/', '', (string) $empresa->cnpj) ?? '';

            if (strlen($cnpj) !== 14) {
                continue;
            }

            try {
                $daEmpresa = [];

                foreach ($this->client->provisoriasPorCnpj($cnpj) as $resumo) {
                    $detalhe = $this->client->tvlPorNumero($resumo->numero);
                    $item = $detalhe === null ? null : $this->item($detalhe, $empresa);

                    if ($item !== null) {
                        $daEmpresa[] = $item;
                    }
                }
            } catch (\Throwable $e) {
                $this->registrarFalha($e);
                $indisponivel = true;

                continue;
            }

            foreach ($daEmpresa as $item) {
                $itens[$item['numero']] ??= $item;
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
        $itens = [];

        try {
            if (preg_match('/^\d{7}$/', $numero) === 1) {
                $resumos = [(int) $numero];
            } elseif (preg_match('/^\d{10}-\d{8}\/\d{4}$/', $numero) === 1) {
                $resumos = array_map(fn ($tvl): int => $tvl->numero, $this->client->tvlsDoProcesso($numero));
            } else {
                $resumos = [];
            }

            foreach ($resumos as $numeroTvl) {
                $detalhe = $this->client->tvlPorNumero($numeroTvl);
                $item = $detalhe === null ? null : $this->item($detalhe, $empresa);

                if ($item !== null) {
                    $itens[$item['numero']] ??= $item;
                }
            }
        } catch (\Throwable $e) {
            $this->registrarFalha($e);

            return ['itens' => [], 'indisponivel' => true];
        }

        return ['itens' => array_values($itens), 'indisponivel' => false];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function item(TvlLegadaDetalhe $tvl, Company $empresa): ?array
    {
        $titularidade = $this->elegibilidade->titularidade($tvl, $empresa);

        if ($titularidade->consultaIndisponivel) {
            throw new LegadoIndisponivelException('Conferência de titularidade indisponível.');
        }

        if (! $titularidade->titular) {
            return null;
        }

        $veredito = $this->elegibilidade->avaliarAposTitularidade($tvl);

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
