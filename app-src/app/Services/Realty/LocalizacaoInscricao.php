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

    /** Códigos de situação cadastral que contam como inscrição ativa: 0 (Ativo) e 15 (Ativa - Dec. 31.232/2019). */
    public const SITUACOES_ATIVAS = ['0', '15'];

    public function __construct(
        public string $status,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?string $codLogTributario = null,
        public ?string $logradouroTributario = null,
        public ?PropertyLocationSource $origemPonto = null,
        public ?GeocodeResult $geocode = null,
        public ?string $situacaoCadastral = null,
        public ?string $utilizacao = null,
    ) {}

    /**
     * Situação cadastral no Cadastro Imobiliário: só 0 e 15 são inscrição ativa.
     * Qualquer outro valor informado é inscrição com pendência. Sem consulta ou
     * sem o dado, não há como afirmar que não está ativa — devolve false.
     */
    public function inscricaoInativa(): bool
    {
        $situacao = trim((string) $this->situacaoCadastral);

        return $situacao !== '' && ! in_array($situacao, self::SITUACOES_ATIVAS, true);
    }

    /**
     * Uso do imóvel no Cadastro Imobiliário (campo Utilizacao): só "NÃO
     * RESIDENCIAL ..." conta. Sem o dado, residencial ou misto, devolve false.
     */
    public function usoNaoResidencial(): bool
    {
        $uso = mb_strtolower(trim((string) $this->utilizacao));
        $uso = strtr($uso, ['ã' => 'a', 'á' => 'a', 'â' => 'a']);

        return str_starts_with($uso, 'nao residencial');
    }

    public function temPonto(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
