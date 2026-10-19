<?php

namespace App\Services\Realty;

use App\Enums\PropertyLocationSource;
use App\Services\Geo\GeocodeResult;

/**
 * Localização de uma inscrição no Cadastro Imobiliário (SEFAZ via SEDUR).
 * Sem coordenada no cadastro, o ponto pode vir da geocodificação do
 * endereço devolvido (logradouro e número). Status diferente de `localizada`
 * nunca traz coordenada.
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
        public ?PropertyLocationSource $origemPonto = null,
        public ?GeocodeResult $geocode = null,
        public ?string $situacaoCadastral = null,
    ) {}

    /**
     * Situação cadastral no Cadastro Imobiliário: "0" é inscrição ativa. Outro
     * valor informado é inscrição com pendência. Sem consulta ou sem o dado,
     * não há como afirmar que não está ativa — devolve false.
     */
    public function inscricaoInativa(): bool
    {
        $situacao = trim((string) $this->situacaoCadastral);

        return $situacao !== '' && $situacao !== '0';
    }

    public function temPonto(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
