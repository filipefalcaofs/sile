<?php

namespace App\Services\Realty;

/**
 * Localização de uma inscrição no Cadastro Imobiliário (SEFAZ via SEDUR).
 * Ponto e logradouro tributário só existem quando a consulta os devolveu —
 * status diferente de `localizada` nunca traz coordenada inventada.
 */
final readonly class LocalizacaoInscricao
{
    public const LOCALIZADA = 'localizada';

    public const SEM_COORDENADA = 'sem_coordenada';

    public const NAO_ENCONTRADA = 'nao_encontrada';

    public const INDISPONIVEL = 'indisponivel';

    public const SEM_INSCRICAO = 'sem_inscricao';

    public function __construct(
        public string $status,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?string $codLogTributario = null,
        public ?string $logradouroTributario = null,
    ) {}

    public function temPonto(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
