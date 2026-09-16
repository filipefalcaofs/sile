<?php

namespace App\Services\Regin;

use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;

/**
 * Aplica o motor REAL sobre um protocolo SEDUR, com tipo de imóvel e área
 * tratados como se tivessem chegado do REGIN. Não chama o REGIN — a origem
 * no relatório é sempre simulacao_protocolo.
 */
class ReginProtocoloSimulacaoService
{
    public const AVISO = 'Dado aplicado como simulação: tipo de imóvel e área como se tivessem chegado do REGIN. A integração REGIN continua indisponível.';

    public function __construct(
        private ReginProtocoloCatalog $catalogo,
        private RiscoClassificationService $risco,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        return array_map(fn (array $p): array => [
            'codigo' => $p['codigo'],
            'rotulo' => $p['rotulo'],
            'processo' => $p['processo'],
            'servico' => $p['servico'],
            'tipo_imovel' => $p['tipo_imovel'],
            'area_utilizada' => $p['area_utilizada'],
            'zona' => $p['zona'],
            'via' => $p['via'],
            'atividades' => count($p['atividades'] ?? []),
        ], $this->catalogo->todos());
    }

    /**
     * @return array<string, mixed>
     */
    public function simular(string $codigo): array
    {
        $protocolo = $this->catalogo->porCodigo($codigo);
        $tipo = TipoImovel::fromRegin(
            isset($protocolo['tipo_imovel']) ? (string) $protocolo['tipo_imovel'] : null,
            TipoImovelCatalog::sedur200826(),
        );
        $area = isset($protocolo['area_utilizada']) ? (float) $protocolo['area_utilizada'] : null;

        $porCnae = [];

        foreach ($protocolo['atividades'] ?? [] as $atividade) {
            $cnae = (string) ($atividade['cnae'] ?? '');
            $respostas = $this->respostas($atividade['perguntas'] ?? []);

            $result = $this->risco->classify(new RiscoInput(
                cnaeCode: $cnae,
                respostasCondicionantes: $respostas,
                areaUtilizada: $area,
                tipoImovel: $tipo,
            ));

            $porCnae[] = [
                'cnae' => $cnae,
                'perguntas' => $atividade['perguntas'] ?? [],
                'risco' => $result->toArray(),
            ];
        }

        return [
            'origem' => 'simulacao_protocolo',
            'aviso' => self::AVISO,
            'codigo' => $protocolo['codigo'],
            'rotulo' => $protocolo['rotulo'],
            'processo' => $protocolo['processo'],
            'servico' => $protocolo['servico'],
            'zona' => $protocolo['zona'] ?? null,
            'via' => $protocolo['via'] ?? null,
            'area_utilizada' => $area,
            'tipo_imovel' => $protocolo['tipo_imovel'],
            'tipo_imovel_normalized' => $tipo->normalized,
            'tipo_imovel_reconhecimento' => $tipo->reconhecimento->value,
            'tipo_imovel_dirige_regra' => $tipo->dirigeRegra(),
            'tipo_imovel_permite_decisao_automatica' => $tipo->permiteDecisaoAutomatica(),
            'por_cnae' => $porCnae,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $perguntas
     * @return array<int|string, bool>
     */
    private function respostas(array $perguntas): array
    {
        $mapa = [];

        foreach ($perguntas as $pergunta) {
            if (! array_key_exists('valor', $pergunta) || ! is_bool($pergunta['valor'])) {
                continue;
            }

            if (! empty($pergunta['codigo'])) {
                $mapa[(string) $pergunta['codigo']] = $pergunta['valor'];
            }

            if (! empty($pergunta['texto'])) {
                $mapa[(string) $pergunta['texto']] = $pergunta['valor'];
            }
        }

        return $mapa;
    }
}
