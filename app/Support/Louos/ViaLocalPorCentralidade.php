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
    /** @var list<string> */
    private const ZONAS = ['zcme', 'zcmu', 'zclme', 'zclmu'];

    /**
     * 9609-2/07 (alojamento de animais domésticos, inclusive hotel, creche e
     * adestramento) e 8011-1/02 (adestramento de cães de guarda).
     *
     * @var list<string>
     */
    private const CNAES_VEDADOS_EM_VIA_LOCAL = ['9609207', '8011102'];

    public static function libera(?string $zona, ?string $classeVia, string $cnae, bool $usoNaoResidencial): bool
    {
        if (! $usoNaoResidencial || $classeVia !== 'VL') {
            return false;
        }

        $chaveDaZona = strtolower((string) preg_replace('/[\s\-_]+/', '', (string) $zona));

        if (! in_array($chaveDaZona, self::ZONAS, true)) {
            return false;
        }

        return ! in_array((string) preg_replace('/\D/', '', $cnae), self::CNAES_VEDADOS_EM_VIA_LOCAL, true);
    }
}
