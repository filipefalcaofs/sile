<?php

namespace App\Services\Renovacao;

use App\Services\Cnpj\CnpjData;
use App\Services\Legado\TvlLegadaDetalhe;
use Illuminate\Support\Str;

/**
 * Confere se o CNPJ informado é o titular de uma TVL legada sem CNPJ gravado.
 *
 * Exige razão social igual (normalizada, sem fuzzy) e endereço igual: mesmo CEP,
 * ou mesmo logradouro e número. A inscrição imobiliária da TVL não consta do
 * cadastro do CNPJ, então não participa daqui; quem a tiver de outra fonte
 * decide fora desta classe.
 */
class ConferenciaTitularidade
{
    private const SUFIXOS_SOCIETARIOS = [
        ['s', 'a'],
        ['ltda'],
        ['me'],
        ['epp'],
        ['eireli'],
        ['sa'],
        ['ss'],
        ['mei'],
    ];

    private const TIPOS_LOGRADOURO = [
        'rua' => 'rua', 'r' => 'rua',
        'avenida' => 'avenida', 'av' => 'avenida',
        'travessa' => 'travessa', 'tv' => 'travessa', 'trav' => 'travessa',
        'alameda' => 'alameda', 'al' => 'alameda',
        'praca' => 'praca', 'pc' => 'praca',
        'estrada' => 'estrada', 'estr' => 'estrada',
        'largo' => 'largo', 'lg' => 'largo',
        'rodovia' => 'rodovia', 'rod' => 'rodovia',
    ];

    public function conferir(TvlLegadaDetalhe $tvl, CnpjData $empresa): ConferenciaResultado
    {
        $razaoLegada = self::normalizarRazao($tvl->razaoSocial ?? '');

        if ($razaoLegada === '') {
            return new ConferenciaResultado(
                false,
                'A viabilidade anterior não tem razão social registrada para conferir a titularidade.',
            );
        }

        if ($razaoLegada !== self::normalizarRazao($empresa->legalName)) {
            return new ConferenciaResultado(
                false,
                'A razão social deste CNPJ não confere com a da viabilidade anterior.',
            );
        }

        if (! $this->enderecoConfere($tvl, $empresa)) {
            return new ConferenciaResultado(
                false,
                'O endereço deste CNPJ não confere com o da viabilidade anterior.',
            );
        }

        return new ConferenciaResultado(true);
    }

    public static function normalizarRazao(string $texto): string
    {
        $tokens = self::tokens($texto);

        do {
            $removido = false;
            foreach (self::SUFIXOS_SOCIETARIOS as $sufixo) {
                $tamanho = count($sufixo);
                if (count($tokens) > $tamanho && array_slice($tokens, -$tamanho) === $sufixo) {
                    $tokens = array_slice($tokens, 0, -$tamanho);
                    $removido = true;
                    break;
                }
            }
        } while ($removido);

        return implode(' ', $tokens);
    }

    private function enderecoConfere(TvlLegadaDetalhe $tvl, CnpjData $empresa): bool
    {
        $cepLegado = $this->digitos($tvl->endereco['cep'] ?? null);
        $cepEmpresa = $this->digitos($empresa->zipCode);

        if ($cepLegado !== '' && $cepLegado === $cepEmpresa) {
            return true;
        }

        $logradouroLegado = $this->normalizarLogradouro($tvl->endereco['logradouro'] ?? null);
        $numeroLegado = $this->digitos($tvl->endereco['numero'] ?? null);

        return $logradouroLegado !== ''
            && $numeroLegado !== ''
            && $logradouroLegado === $this->normalizarLogradouro($empresa->street)
            && $numeroLegado === $this->digitos($empresa->number);
    }

    private function normalizarLogradouro(?string $logradouro): string
    {
        $tokens = self::tokens($logradouro ?? '');

        if ($tokens === [] || ctype_digit(implode('', $tokens))) {
            return '';
        }

        $tokens[0] = self::TIPOS_LOGRADOURO[$tokens[0]] ?? $tokens[0];

        return implode(' ', $tokens);
    }

    private function digitos(?string $valor): string
    {
        return preg_replace('/\D/', '', $valor ?? '') ?? '';
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $texto): array
    {
        $limpo = preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($texto))) ?? '';

        return preg_split('/ /', trim($limpo), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
