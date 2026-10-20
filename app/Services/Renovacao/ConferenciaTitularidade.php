<?php

namespace App\Services\Renovacao;

use App\Services\Cnpj\CnpjData;
use App\Services\Legado\TvlLegadaDetalhe;
use Illuminate\Support\Str;

/**
 * Confere se o CNPJ informado é o titular de uma TVL legada sem CNPJ gravado.
 *
 * Exige razão social igual (normalizada, sem fuzzy) e que o endereço do CNPJ seja
 * o do imóvel da TVL, vindo do cadastro imobiliário e não do endereço gravado no
 * legado: mesmo CEP, ou mesmo logradouro e número. O cadastro só é consultado
 * depois de a razão social conferir. CEP que não tenha oito dígitos ou seja só
 * zeros, e número sem dígito diferente de zero, contam como ausentes.
 */
class ConferenciaTitularidade
{
    public const M_DIVERGE = 'O endereço deste CNPJ não confere com o do imóvel da viabilidade anterior. Procure a SEDUR.';

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

    /**
     * @param  callable(): EnderecoDoImovel  $enderecoDoImovel
     *
     * @throws EnderecoImovelIndisponivelException
     */
    public function conferir(TvlLegadaDetalhe $tvl, CnpjData $empresa, callable $enderecoDoImovel): ConferenciaResultado
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

        try {
            $imovel = $enderecoDoImovel();
        } catch (EnderecoImovelNaoEncontradoException $e) {
            return new ConferenciaResultado(false, $e->getMessage());
        }

        if (! $this->enderecoConfere($imovel, $empresa)) {
            return new ConferenciaResultado(false, self::M_DIVERGE);
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

    private function enderecoConfere(EnderecoDoImovel $imovel, CnpjData $empresa): bool
    {
        [$cepEmpresa, $logradouroEmpresa, $numeroEmpresa] = $this->enderecoDaEmpresa($empresa);

        $cepImovel = $this->cepUtilizavel($imovel->cep);

        if ($cepImovel !== '' && $cepImovel === $cepEmpresa) {
            return true;
        }

        $logradouroImovel = $this->normalizarLogradouro($imovel->logradouro);
        $numeroImovel = $this->numeroUtilizavel($imovel->numero);

        return $logradouroImovel !== ''
            && $numeroImovel !== ''
            && $logradouroImovel === $logradouroEmpresa
            && $numeroImovel === $numeroEmpresa;
    }

    /**
     * Endereço da empresa como [cep, logradouro normalizado, número], com '' no
     * que não puder ser lido. A consulta pela SEDUR traz tudo em `street`
     * ("Rua X, 10, BAIRRO, 41830000"), sem CEP nem número separados; a BrasilAPI
     * traz os campos separados.
     *
     * @return array{string, string, string}
     */
    private function enderecoDaEmpresa(CnpjData $empresa): array
    {
        $cep = $this->cepUtilizavel($empresa->zipCode);

        if ($cep !== '' || trim((string) $empresa->number) !== '') {
            return [$cep, $this->normalizarLogradouro($empresa->street), $this->numeroUtilizavel($empresa->number)];
        }

        $composto = (string) $empresa->street;
        $segmentos = array_map('trim', explode(',', $composto));

        preg_match_all('/\b\d{5}-?\d{3}\b/', $composto, $ceps);
        $ultimoCep = $ceps[0] === [] ? null : end($ceps[0]);

        $numero = isset($segmentos[1]) && preg_match('/^\d+\s*[A-Za-z]?$/', $segmentos[1]) === 1
            ? $this->numeroUtilizavel($segmentos[1])
            : '';

        return [$this->cepUtilizavel($ultimoCep), $this->normalizarLogradouro($segmentos[0]), $numero];
    }

    private function cepUtilizavel(?string $cep): string
    {
        $digitos = $this->digitos($cep);

        return strlen($digitos) === 8 && trim($digitos, '0') !== '' ? $digitos : '';
    }

    private function numeroUtilizavel(?string $numero): string
    {
        $digitos = $this->digitos($numero);

        return trim($digitos, '0') !== '' ? $digitos : '';
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
