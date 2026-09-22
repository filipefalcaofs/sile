<?php

namespace App\Services\Risco;

use App\Enums\TipoImovelReconhecimento;

/**
 * Valor de tipo de imóvel normalizado a partir do REGIN. O motor só decide
 * automaticamente quando o reconhecimento é DirigeRegra ou RamoComum.
 */
final readonly class TipoImovel
{
    public function __construct(
        public ?string $raw,
        public ?string $normalized,
        public TipoImovelReconhecimento $reconhecimento,
    ) {}

    public static function fromRegin(?string $raw, TipoImovelCatalog $catalog): self
    {
        if ($raw === null || trim($raw) === '') {
            return new self(
                raw: $raw,
                normalized: null,
                reconhecimento: TipoImovelReconhecimento::Ausente,
            );
        }

        $normalized = self::normalize($raw);

        $dirige = $catalog->codigoQueDirige($normalized);
        if ($dirige !== null) {
            return new self($raw, $dirige, TipoImovelReconhecimento::DirigeRegra);
        }

        $comum = $catalog->codigoRamoComum($normalized);
        if ($comum !== null) {
            return new self($raw, $comum, TipoImovelReconhecimento::RamoComum);
        }

        return new self($raw, null, TipoImovelReconhecimento::Desconhecido);
    }

    public function dirigeRegra(): bool
    {
        return $this->reconhecimento === TipoImovelReconhecimento::DirigeRegra;
    }

    public function permiteDecisaoAutomatica(): bool
    {
        return $this->reconhecimento === TipoImovelReconhecimento::DirigeRegra
            || $this->reconhecimento === TipoImovelReconhecimento::RamoComum;
    }

    public static function normalize(string $raw): string
    {
        $texto = mb_strtolower(trim($raw));
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o',
            'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }
}
