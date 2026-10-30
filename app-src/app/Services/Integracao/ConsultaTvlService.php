<?php

namespace App\Services\Integracao;

use App\Enums\IntencaoAtividade;
use App\Enums\TvlKind;
use App\Models\Cnae;
use App\Models\TllValor;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\TllCalculoService;
use Carbon\CarbonInterface;

/**
 * Resposta da consulta de Viabilidade que a SEFAZ/SIGS faz no Viabiliza (alvará e DAM),
 * no mesmo JSON do WS SIGS `buscarPorNumeroTVLRegin`. Só leitura: monta o
 * contrato a partir da decisão, da ficha e do cálculo da TLL já gravados.
 * Campos que o Viabiliza não guarda saem null, nunca inventados.
 */
class ConsultaTvlService
{
    public const NAO_ENCONTRADO = '00';

    public const CONCEDIDO = '01';

    public const INVALIDO = '02';

    public const ERRO = '99';

    private const MENSAGENS = [
        self::NAO_ENCONTRADO => 'TVL não encontrado.',
        self::CONCEDIDO => 'TVL concedido.',
        self::INVALIDO => 'TVL Inválido, não deferido.',
        self::ERRO => 'Erro no processamento.',
    ];

    /**
     * Marca o valor monetário para o controller escrevê-lo no JSON como número
     * com duas casas (206.10): o json_encode do PHP corta o zero final.
     */
    public const MARCA_DECIMAL = '@@decimal:';

    private const COD_MUNICIPIO = '38490';

    private const TIPOS_LOGRADOURO = [
        'Rua', 'Avenida', 'Travessa', 'Alameda', 'Largo', 'Praça', 'Estrada', 'Ladeira',
        'Rodovia', 'Via', 'Beco', 'Vila', 'Caminho', 'Loteamento', 'Condomínio', 'Parque',
    ];

    public function __construct(private readonly TllCalculoService $tll) {}

    /**
     * @return array{resposta: array<string, string>}
     */
    public function semResposta(string $numero, string $status): array
    {
        return ['resposta' => $this->resposta($numero, $status, '')];
    }

    /**
     * Null quando nenhuma decisão carrega esse número de Viabilidade.
     *
     * @return array<string, mixed>|null
     */
    public function consultar(string $numero): ?array
    {
        $decisao = ViabilityDecision::query()
            ->whereIn('tvl_product_number', array_unique([$numero, 'TVL-'.$numero]))
            ->with(['viabilityRequest.company', 'viabilityRequest.currentAnalysisRecord'])
            ->first();

        if ($decisao === null) {
            return null;
        }

        $processo = $decisao->viabilityRequest;
        $status = $decisao->isDeferida() ? self::CONCEDIDO : self::INVALIDO;
        $cnpj = $this->cnpjDaEmpresa($processo);

        return [
            'resposta' => $this->resposta($numero, $status, $cnpj),
            'empresa' => $this->empresa($decisao, $processo),
        ];
    }

    /**
     * Só CNPJ de verdade. Na empresa a constituir o REGIN manda o CPF de quem
     * pediu e a coluna `cnpj` guarda esse CPF; pedido sem documento grava 14
     * zeros. Nos dois casos a consulta devolve vazio.
     */
    private function cnpjDaEmpresa(?ViabilityRequest $processo): string
    {
        $documento = preg_replace('/\D/', '', (string) $processo?->company?->cnpj) ?? '';

        if (strlen($documento) !== 14 || trim($documento, '0') === '') {
            return '';
        }

        return $documento;
    }

    /**
     * @return array<string, string>
     */
    private function resposta(string $numero, string $status, string $cnpj): array
    {
        return [
            'protocolo' => $numero,
            'status' => $status,
            'mensagem' => self::MENSAGENS[$status],
            'cnpj_instituicao' => $cnpj,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empresa(ViabilityDecision $decisao, ?ViabilityRequest $processo): array
    {
        $company = $processo?->company;
        [$tipoLogradouro, $logradouro] = $this->separarLogradouro($processo?->address_street);
        $condicionantes = $this->condicionantes($decisao, $processo);
        $tipo = $this->tipoEfetivo($decisao);

        return [
            'nome_requerente' => $company?->legal_name,
            'inscricao_imobiliaria' => $processo?->property_registration,
            'area_utilizada' => $processo?->used_area_m2 === null ? null : number_format((float) $processo->used_area_m2, 2, ',', '.'),
            'tipo_imovel' => $processo?->tipo_imovel ?? $processo?->tipo_imovel_cadastro,
            'porte_empresa' => $company?->size,
            'cep' => $processo?->address_zip === null ? null : preg_replace('/\D/', '', $processo->address_zip),
            'uf' => 'BA',
            'cod_municipio' => self::COD_MUNICIPIO,
            'municipio' => 'SALVADOR',
            'bairro' => $processo?->address_neighborhood,
            'cod_log' => $processo?->cod_log,
            'tipo_logradouro' => $tipoLogradouro,
            'logradouro' => $logradouro,
            'numero' => $processo?->address_number,
            'complemento' => $processo?->address_complement,
            'processo' => null,
            'tipoTVL' => $this->tipoTvl($tipo),
            'zona' => $processo?->zona_manual ?? $processo?->zona_codigo,
            'via' => $processo?->via_manual ?? ($processo?->camadas_incidentes['via'] ?? null),
            'hashDam' => null,
            'observacao' => $condicionantes,
            'data_vencimento' => $this->dataHora($decisao->tvl_valid_until),
            'data_viabilidade' => $this->dataHora($decisao->decided_at),
            'data_ini_viabilidade' => $processo?->protocoled_at?->format('Ymd His'),
            'data_fim_viabilidade' => $decisao->decided_at?->format('Ymd His'),
            'nome_impresso_TVL' => $company?->legal_name,
            'tipo_viabilidade' => $this->tipoViabilidade($tipo),
            'sede_escritorio_virtual' => $decisao->is_virtual_office_hq ? '1' : '0',
            'grupo_cnae' => $this->grupoCnae($decisao),
            'tvlSimplifica' => false,
            'taxas' => $this->taxas($decisao),
            'condicionantes' => $condicionantes,
            'bap' => $processo?->external_reference,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function separarLogradouro(?string $endereco): array
    {
        $endereco = trim((string) $endereco);

        if ($endereco === '') {
            return [null, null];
        }

        $partes = explode(' ', $endereco, 2);

        foreach (self::TIPOS_LOGRADOURO as $tipo) {
            if (isset($partes[1]) && mb_strtolower($partes[0]) === mb_strtolower($tipo)) {
                return [$tipo, trim($partes[1])];
            }
        }

        return [null, $endereco];
    }

    /**
     * O tipo só é gravado na conclusão feita por analista. A decisão deferida
     * automaticamente (fluxo expresso) não tem tipo nem validade, e vale como
     * definitiva.
     */
    private function tipoEfetivo(ViabilityDecision $decisao): ?TvlKind
    {
        return $decisao->tvl_kind ?? ($decisao->isDeferida() ? TvlKind::Definitiva : null);
    }

    private function tipoTvl(?TvlKind $tipo): ?string
    {
        return match ($tipo) {
            TvlKind::Definitiva => 'Definitivo',
            TvlKind::Provisoria => 'Provisório',
            TvlKind::PreOperacional => 'Pré-operacional',
            null => null,
        };
    }

    private function tipoViabilidade(?TvlKind $tipo): ?int
    {
        return match ($tipo) {
            TvlKind::Definitiva => 1,
            TvlKind::Provisoria, TvlKind::PreOperacional => 2,
            null => null,
        };
    }

    private function dataHora(?CarbonInterface $data): ?string
    {
        return $data?->format('d/m/Y H:i:s');
    }

    /**
     * Condicionantes gerais da ficha finalizada e as de cada CNAE da decisão,
     * numeradas ("1-..."), uma por linha e sem repetir texto.
     */
    private function condicionantes(ViabilityDecision $decisao, ?ViabilityRequest $processo): ?string
    {
        $fontes = [$processo?->currentAnalysisRecord?->conditions ?? []];

        foreach ($decisao->per_cnae ?? [] as $item) {
            $fontes[] = is_array($item) ? ($item['condicionantes'] ?? []) : [];
        }

        $itens = [];

        foreach ($fontes as $lista) {
            foreach (is_array($lista) ? $lista : [] as $condicao) {
                $texto = is_array($condicao) ? ($condicao['descricao'] ?? null) : $condicao;
                $texto = is_string($texto) ? trim($texto) : '';

                if ($texto !== '' && ! in_array($texto, $itens, true)) {
                    $itens[] = $texto;
                }
            }
        }

        if ($itens === []) {
            return null;
        }

        return implode("\r\n", array_map(static fn (string $texto, int $i): string => ($i + 1).'-'.$texto, $itens, array_keys($itens)));
    }

    /**
     * @return list<array<string, string>>
     */
    private function grupoCnae(ViabilityDecision $decisao): array
    {
        $itens = array_values(array_filter(
            is_array($decisao->per_cnae) ? $decisao->per_cnae : [],
            static fn ($item): bool => is_array($item)
                && isset($item['cnae'])
                && ($item['intencao'] ?? null) !== IntencaoAtividade::Excluir->value,
        ));

        $descricoes = Cnae::query()
            ->whereIn('code', array_map(static fn (array $item): string => (string) $item['cnae'], $itens))
            ->pluck('description', 'code');

        return array_map(static fn (array $item): array => [
            'codigo_cnae' => (string) $item['cnae'],
            'tipo_cnae' => '2',
            'categoria' => (string) ($item['categoria'] ?? ''),
            'descricao' => (string) ($descricoes[(string) $item['cnae']] ?? ''),
        ], $itens);
    }

    /**
     * Taxa da TLL do deferimento, a mesma calculada para o DAM (HU-145). Vazia
     * quando o exercício não tem valor parametrizado.
     *
     * @return list<array<string, mixed>>
     */
    private function taxas(ViabilityDecision $decisao): array
    {
        $exercicio = (int) ($decisao->decided_at?->year ?? now()->year);
        $itens = [];

        foreach ($decisao->per_cnae ?? [] as $item) {
            $codigoTll = $item['codigo_tll'] ?? null;

            if (! is_string($codigoTll) || $codigoTll === '') {
                continue;
            }

            $itens[] = [
                'codigo_tll' => $codigoTll,
                'exige_fator_multiplicador' => (bool) (Cnae::query()->where('code', (string) ($item['cnae'] ?? ''))->value('exige_fator_multiplicador') ?? false),
            ];
        }

        $calculo = $this->tll->calcular($itens, $exercicio);

        if ($calculo === null) {
            return [];
        }

        $tll = TllValor::resolver($calculo->codigo_tll, $exercicio, null);

        return [[
            'documento' => $decisao->viabilityRequest?->protocol_number,
            'codigoTLL' => $tll?->codigo_tll_sefaz ?? $calculo->codigo_tll,
            'valor' => self::MARCA_DECIMAL.number_format((float) $calculo->valor, 2, '.', '').'@@',
            'dataTaxa' => $decisao->decided_at?->format('d/m/Y'),
            'servico' => $tll?->servico_sefaz ?? 'Viabilidade de Localização',
            'codigoServico' => $tll?->codigo_servico_sefaz,
        ]];
    }
}
