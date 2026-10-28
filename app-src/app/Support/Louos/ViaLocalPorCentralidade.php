<?php

namespace App\Support\Louos;

/**
 * Nota do Quadro 11A da LOUOS: os usos não residenciais podem se instalar em Via
 * Local (VL) quando situados em ZCMe, ZCMu, ZCLMe e ZCLMu, desde que permitidos
 * para essas zonas. A observação (a) mantém vedados em VL o adestramento e a
 * hospedagem de animais de estimação.
 */
final class ViaLocalPorCentralidade
{
    /** A nota liberou o uso não residencial em Via Local. */
    public const LIBERADA = 'liberada';

    /** A observação (a) veda a atividade em Via Local, mesmo em zona da nota. */
    public const VEDADA_PELA_OBSERVACAO = 'vedada_pela_observacao';

    /** Sigla da zona, com ou sem número, subzona ou bairro depois (ZCMu 2, ZCMe 1/01, ZCMu 1 - IPITANGA). */
    private const PADRAO_ZONA = '/^(zcme|zcmu|zclme|zclmu)(?![a-z])/';

    /**
     * Padrão da lista, usado quando o parâmetro `louos.via_local.cnaes_vedados`
     * não existe: 9609-2/07 (alojamento de animais domésticos, inclusive hotel,
     * creche e adestramento) e 8011-1/02 (adestramento de cães de guarda). A SEDUR
     * ainda valida a associação definitiva; 9609-2/08 não entra por inferência.
     *
     * @var list<string>
     */
    private const CNAES_VEDADOS_EM_VIA_LOCAL = ['9609207', '8011102'];

    /**
     * @param  list<string>|null  $cnaesVedados  só dígitos; null usa o padrão
     * @return self::LIBERADA|self::VEDADA_PELA_OBSERVACAO|null null quando a nota não se aplica (via, uso ou zona fora dela)
     */
    public static function avaliar(?string $zona, ?string $classeVia, string $cnae, bool $usoNaoResidencial, ?array $cnaesVedados = null): ?string
    {
        if (! $usoNaoResidencial || $classeVia !== 'VL') {
            return null;
        }

        if (preg_match(self::PADRAO_ZONA, strtolower(trim((string) $zona))) !== 1) {
            return null;
        }

        $vedados = $cnaesVedados ?? self::CNAES_VEDADOS_EM_VIA_LOCAL;

        return in_array((string) preg_replace('/\D/', '', $cnae), $vedados, true)
            ? self::VEDADA_PELA_OBSERVACAO
            : self::LIBERADA;
    }

    /**
     * @param  list<string>|null  $cnaesVedados
     */
    public static function libera(?string $zona, ?string $classeVia, string $cnae, bool $usoNaoResidencial, ?array $cnaesVedados = null): bool
    {
        return self::avaliar($zona, $classeVia, $cnae, $usoNaoResidencial, $cnaesVedados) === self::LIBERADA;
    }

    /**
     * Lista de CNAEs do parâmetro: texto separado por vírgula ou ponto e vírgula,
     * com ou sem máscara.
     *
     * @return list<string>
     */
    public static function cnaesDaConfiguracao(?string $texto): array
    {
        $codigos = array_map(
            fn (string $parte): string => (string) preg_replace('/\D/', '', $parte),
            preg_split('/[,;]/', (string) $texto) ?: [],
        );

        return array_values(array_unique(array_filter($codigos, fn (string $codigo): bool => $codigo !== '')));
    }

    /**
     * Padrão da lista, em texto, para o parâmetro administrável.
     */
    public static function padraoDosCnaesVedados(): string
    {
        return implode(',', self::CNAES_VEDADOS_EM_VIA_LOCAL);
    }
}
