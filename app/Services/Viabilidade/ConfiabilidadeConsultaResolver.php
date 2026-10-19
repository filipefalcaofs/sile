<?php

namespace App\Services\Viabilidade;

use App\Enums\ConfiabilidadeConsulta;
use App\Enums\ResultadoViabilidade;
use App\Services\Decisao\DecisionTextCatalog;

/**
 * Classifica a confiabilidade de uma consulta prévia a partir do que os motores
 * já devolveram — não decide veredito nem recalcula nada. Dado que o cidadão pode
 * informar e não informou torna a consulta incompleta; veredito pendente do motor
 * (zona/via não identificada, sem local, CNAE sem regra) a torna inconclusiva.
 */
final class ConfiabilidadeConsultaResolver
{
    public function __construct(private DecisionTextCatalog $textos) {}

    public function resolver(ConsultaViabilidadeResult $result): ConfiabilidadeConsultaResultado
    {
        $pendencias = $this->pendencias($result);

        if ($pendencias !== []) {
            return new ConfiabilidadeConsultaResultado(ConfiabilidadeConsulta::Incompleta, $pendencias);
        }

        $veredito = $result->vereditoLocacional();

        if ($veredito['resultado'] === ResultadoViabilidade::Pendente->value) {
            return new ConfiabilidadeConsultaResultado(
                ConfiabilidadeConsulta::Inconclusiva,
                [],
                $veredito['motivo'] ?? $result->avisos[0] ?? null,
            );
        }

        return new ConfiabilidadeConsultaResultado(ConfiabilidadeConsulta::Conclusiva);
    }

    /**
     * @return list<array{campo: string, rotulo: string, itens?: list<string>}>
     */
    private function pendencias(ConsultaViabilidadeResult $result): array
    {
        $ramo = $result->enquadramento->enquadramento;
        $pendencias = [];

        $area = (float) ($result->entrada['area'] ?? 0);
        $exigeArea = ($result->entrada['tipo'] ?? null) !== ConsultaViabilidadeInput::TIPO_CNAE;

        if (($exigeArea && $area <= 0) || ($ramo['dado_faltante'] ?? null) === 'area') {
            $pendencias[] = ['campo' => 'area', 'rotulo' => $this->textos->get('consulta.pendencia.area')];
        }

        $pergunta = $ramo['pergunta_sem_resposta'] ?? null;

        if (is_array($pergunta)) {
            $pendencias[] = [
                'campo' => 'pergunta',
                'rotulo' => "Pergunta {$pergunta['numero']}: {$pergunta['enunciado']}",
            ];
        }

        foreach (['tipo_imovel', 'categoria_empresa'] as $campo) {
            if (($ramo['dado_faltante'] ?? null) === $campo) {
                $pendencias[] = ['campo' => $campo, 'rotulo' => $this->textos->get("consulta.pendencia.{$campo}")];
            }
        }

        $vagas = $this->vagasExigidasSemDeclaracao($result);

        if ($vagas !== []) {
            $pendencias[] = [
                'campo' => 'vagas',
                'rotulo' => $this->textos->get('consulta.pendencia.vagas'),
                'itens' => $vagas,
            ];
        }

        return $pendencias;
    }

    /**
     * @return list<string>
     */
    private function vagasExigidasSemDeclaracao(ConsultaViabilidadeResult $result): array
    {
        foreach ($result->enquadramento->consolidado['condicionantes'] ?? [] as $condicionante) {
            if (($condicionante['tipo'] ?? null) !== 'vagas') {
                continue;
            }

            $exigido = $condicionante['exigido'] ?? null;
            $declarado = $condicionante['declarado'] ?? [];

            if (is_array($exigido) && $exigido !== [] && $declarado === []) {
                return array_map(strval(...), array_keys($exigido));
            }
        }

        return [];
    }
}
