<?php

namespace App\Services\Viabilidade;

/**
 * Entrada imutável da consulta prévia de viabilidade (HU-054 a HU-056),
 * espelhando o estilo de EnquadramentoInput/RiscoInput (readonly + named
 * constructors). Há exatamente 3 formas honestas de consultar e cada uma carrega
 * o `tipo` explícito, para que o serviço (07-05) saiba como resolver o ponto:
 *
 * - endereço (HU-054): geocodifica → território → motores (fluxo completo);
 * - CNAE (HU-056): risco real + (com `area`) Quadro 7, sem território;
 * - inscrição (HU-055): resolução do ponto BLOQUEADA pendente SEDUR (contrato
 *   PropertyRegistryLookup) — degrada com aviso, NUNCA inventa o ponto.
 *
 * `cnae` é sempre exigido (a viabilidade é sempre de uma atividade). `area` é o
 * m² pretendido usado pelo Quadro 7 (HU-057): sem área, o Quadro 7 não enquadra
 * e o consolidado do motor degrada para pendente de forma honesta — o veredito
 * locacional nunca é inventado.
 */
final readonly class ConsultaViabilidadeInput
{
    public const TIPO_ENDERECO = 'endereco';

    public const TIPO_CNAE = 'cnae';

    public const TIPO_INSCRICAO = 'inscricao';

    public function __construct(
        public string $tipo,
        public string $cnae,
        public ?float $area = null,
        public ?string $endereco = null,
        public ?string $inscricao = null,
    ) {}

    /**
     * Consulta por endereço (HU-054): o serviço geocodifica o endereço, resolve o
     * território e orquestra os motores. `area` alimenta o Quadro 7 (HU-057).
     */
    public static function paraEndereco(string $endereco, string $cnae, ?float $area = null): self
    {
        return new self(
            tipo: self::TIPO_ENDERECO,
            cnae: $cnae,
            area: $area,
            endereco: $endereco,
        );
    }

    /**
     * Consulta por CNAE (HU-056): risco real e, quando há `area`, Quadro 7 — sem
     * território (sem ponto, sem zona/via). O veredito locacional fica pendente.
     */
    public static function paraCnae(string $cnae, ?float $area = null): self
    {
        return new self(
            tipo: self::TIPO_CNAE,
            cnae: $cnae,
            area: $area,
        );
    }

    /**
     * Consulta por inscrição imobiliária (HU-055): a resolução do ponto depende da
     * base de lotes/Cadastro (pendente SEDUR — contrato PropertyRegistryLookup).
     * Enquanto indisponível, degrada com aviso e sugere o endereço.
     */
    public static function paraInscricao(string $inscricao, string $cnae, ?float $area = null): self
    {
        return new self(
            tipo: self::TIPO_INSCRICAO,
            cnae: $cnae,
            area: $area,
            inscricao: $inscricao,
        );
    }
}
