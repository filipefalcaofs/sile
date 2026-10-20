<?php

namespace App\Services\Legado;

use Carbon\CarbonImmutable;

/**
 * TVL vigente de um número com processo, flags, atividades e renovações.
 */
final readonly class TvlLegadaDetalhe
{
    /**
     * @param  array{logradouro: ?string, numero: ?string, bairro: ?string, cep: ?string}  $endereco
     * @param  array{sede: bool, mei: bool, escritorio_virtual: bool, culto_religioso: bool, edificio_comercial: bool}  $flags
     * @param  list<array{codigo: string, descricao: string}>  $atividades
     * @param  list<array{solicitada_em: ?string, paga_em: ?string}>  $renovacoes
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
        public ?string $processoNumero,
        public ?string $complemento,
        public ?string $zona,
        public ?string $via,
        public array $flags,
        public ?string $condicionanteAdicional,
        public array $atividades,
        public array $renovacoes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $base = TvlLegada::fromArray($data);
        $flags = is_array($data['flags'] ?? null) ? $data['flags'] : [];

        $atividades = [];
        foreach (is_array($data['atividades'] ?? null) ? $data['atividades'] : [] as $atividade) {
            $atividades[] = [
                'codigo' => (string) ($atividade['codigo'] ?? ''),
                'descricao' => (string) ($atividade['descricao'] ?? ''),
            ];
        }

        $renovacoes = [];
        foreach (is_array($data['renovacoes'] ?? null) ? $data['renovacoes'] : [] as $renovacao) {
            $renovacoes[] = [
                'solicitada_em' => TvlLegada::texto($renovacao['solicitada_em'] ?? null),
                'paga_em' => TvlLegada::texto($renovacao['paga_em'] ?? null),
            ];
        }

        return new self(
            numero: $base->numero,
            tipo: $base->tipo,
            deferida: $base->deferida,
            ativa: $base->ativa,
            emissao: $base->emissao,
            validade: $base->validade,
            situacao: $base->situacao,
            diasParaVencer: $base->diasParaVencer,
            renovadaDepois: $base->renovadaDepois,
            ultimaRenovacaoEm: $base->ultimaRenovacaoEm,
            titularDocumento: $base->titularDocumento,
            titularTipo: $base->titularTipo,
            titularOrigem: $base->titularOrigem,
            razaoSocial: $base->razaoSocial,
            inscricaoImobiliaria: $base->inscricaoImobiliaria,
            endereco: $base->endereco,
            processoNumero: TvlLegada::texto($data['processo_numero'] ?? null),
            complemento: TvlLegada::texto($data['complemento'] ?? null),
            zona: TvlLegada::texto($data['zona'] ?? null),
            via: TvlLegada::texto($data['via'] ?? null),
            flags: [
                'sede' => (bool) ($flags['sede'] ?? false),
                'mei' => (bool) ($flags['mei'] ?? false),
                'escritorio_virtual' => (bool) ($flags['escritorio_virtual'] ?? false),
                'culto_religioso' => (bool) ($flags['culto_religioso'] ?? false),
                'edificio_comercial' => (bool) ($flags['edificio_comercial'] ?? false),
            ],
            condicionanteAdicional: TvlLegada::texto($data['condicionante_adicional'] ?? null),
            atividades: $atividades,
            renovacoes: $renovacoes,
        );
    }
}
