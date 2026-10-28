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
     * Provisórias do sistema anterior para um CNPJ, sem vínculo com quem busca.
     * TVL gravada para outro CNPJ não aparece.
     *
     * @return array{itens: list<array<string, mixed>>, indisponivel: bool}
     */
    public function porCnpj(string $cnpj): array
    {
        $itens = [];

        try {
            foreach ($this->client->provisoriasPorCnpj($cnpj) as $resumo) {
                $detalhe = $this->client->tvlPorNumero($resumo->numero);
                $item = $detalhe === null ? null : $this->itemDaBusca($detalhe, $cnpj);

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
     * Provisórias do sistema anterior pelo número da TVL (7 dígitos) ou do
     * processo. O CNPJ da renovação é o gravado na TVL; sem ele, o da busca,
     * se houver.
     *
     * @return array{itens: list<array<string, mixed>>, indisponivel: bool}
     */
    public function porNumero(string $numero, ?string $cnpj = null): array
    {
        $numero = preg_replace('/\s+/', '', trim($numero)) ?? '';
        $itens = [];

        try {
            if (preg_match('/^\d{7}$/', $numero) === 1) {
                $numeros = [(int) $numero];
            } elseif (preg_match('/^\d{10}-\d{8}\/\d{4}$/', $numero) === 1) {
                $numeros = array_map(fn ($tvl): int => $tvl->numero, $this->client->tvlsDoProcesso($numero));
            } else {
                $numeros = [];
            }

            foreach ($numeros as $numeroTvl) {
                $detalhe = $this->client->tvlPorNumero($numeroTvl);
                $item = $detalhe === null ? null : $this->itemDaBusca($detalhe, $cnpj);

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
     * CNPJ de quem renova a TVL: o gravado nela ou, sem ele, o informado.
     */
    public static function cnpjDaRenovacao(TvlLegadaDetalhe $tvl, ?string $cnpjInformado): ?string
    {
        $gravado = preg_replace('/\D/', '', (string) $tvl->titularDocumento) ?? '';

        if (strlen($gravado) === 14) {
            return $gravado;
        }

        $informado = preg_replace('/\D/', '', (string) $cnpjInformado) ?? '';

        return strlen($informado) === 14 ? $informado : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function itemDaBusca(TvlLegadaDetalhe $tvl, ?string $cnpjInformado): ?array
    {
        $cnpj = self::cnpjDaRenovacao($tvl, $cnpjInformado);
        $base = [
            'origem' => 'legado',
            'id' => null,
            'numero' => (string) $tvl->numero,
            'empresa' => $tvl->razaoSocial,
            'cnpj' => $cnpj === null ? null : (new Company(['cnpj' => $cnpj]))->formatted_cnpj,
            'endereco' => $this->endereco($tvl),
            'validade' => $tvl->validade?->format('Y-m-d'),
            'situacao' => $tvl->situacao,
            'dias_para_vencer' => $tvl->diasParaVencer,
        ];

        if ($cnpj === null) {
            return $base + [
                'elegivel' => false,
                'motivo' => 'Busque pelo CNPJ da empresa para renovar esta viabilidade.',
                'url' => null,
            ];
        }

        $titularidade = $this->elegibilidade->titularidade($tvl, new Company(['cnpj' => $cnpj]));

        if ($titularidade->consultaIndisponivel) {
            throw new LegadoIndisponivelException('Conferência de titularidade indisponível.');
        }

        if (! $titularidade->titular) {
            return null;
        }

        $veredito = $this->elegibilidade->avaliarAposTitularidade($tvl);

        return $base + [
            'elegivel' => $veredito->elegivel,
            'motivo' => $veredito->motivo,
            'url' => $veredito->elegivel ? "/portal/renovacao/legado/{$cnpj}/{$tvl->numero}" : null,
        ];
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
