<?php

namespace App\Services\Legado;

use Carbon\CarbonImmutable;

/**
 * TVL vigente de um número, como a API legada de viabilidade a resume.
 */
final readonly class TvlLegada
{
    /**
     * @param  array{logradouro: ?string, numero: ?string, bairro: ?string, cep: ?string}  $endereco
     */
    public function __construct(
        public int $numero,
        public string $tipo,
        public bool $deferida,
        public bool $ativa,
        public ?CarbonImmutable $emissao,
        public ?CarbonImmutable $validade,
        public ?string $situacao,
        public ?int $diasParaVencer,
        public bool $renovadaDepois,
        public ?CarbonImmutable $ultimaRenovacaoEm,
        public ?string $titularDocumento,
        public ?string $titularTipo,
        public ?string $titularOrigem,
        public ?string $razaoSocial,
        public ?string $inscricaoImobiliaria,
        public array $endereco,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $titular = is_array($data['titular'] ?? null) ? $data['titular'] : [];
        $endereco = is_array($data['endereco'] ?? null) ? $data['endereco'] : [];

        return new self(
            numero: (int) $data['numero'],
            tipo: (string) ($data['tipo'] ?? 'desconhecido'),
            deferida: (bool) ($data['deferida'] ?? false),
            ativa: (bool) ($data['ativa'] ?? false),
            emissao: self::data($data['emissao'] ?? null),
            validade: self::data($data['validade'] ?? null),
            situacao: self::texto($data['situacao'] ?? null),
            diasParaVencer: isset($data['dias_para_vencer']) ? (int) $data['dias_para_vencer'] : null,
            renovadaDepois: (bool) ($data['renovada_depois'] ?? false),
            ultimaRenovacaoEm: self::data($data['ultima_renovacao_em'] ?? null),
            titularDocumento: self::texto($titular['documento'] ?? null),
            titularTipo: self::texto($titular['tipo'] ?? null),
            titularOrigem: self::texto($titular['origem'] ?? null),
            razaoSocial: self::texto($data['razao_social'] ?? null),
            inscricaoImobiliaria: self::texto($data['inscricao_imobiliaria'] ?? null),
            endereco: [
                'logradouro' => self::texto($endereco['logradouro'] ?? null),
                'numero' => self::texto($endereco['numero'] ?? null),
                'bairro' => self::texto($endereco['bairro'] ?? null),
                'cep' => self::texto($endereco['cep'] ?? null),
            ],
        );
    }

    public static function data(mixed $valor): ?CarbonImmutable
    {
        return is_string($valor) && $valor !== '' ? CarbonImmutable::parse($valor) : null;
    }

    public static function texto(mixed $valor): ?string
    {
        return is_scalar($valor) && (string) $valor !== '' ? (string) $valor : null;
    }
}
