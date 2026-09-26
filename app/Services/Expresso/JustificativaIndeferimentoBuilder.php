<?php

namespace App\Services\Expresso;

use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Solicitacao\ResolvedViability;
use App\Services\Viabilidade\ConsultaViabilidadeResult;

/**
 * Justificativa do indeferimento expresso pelos quadros da LOUOS: um parágrafo
 * por CNAE não permitido, com o texto padrão do catálogo do quadro que vetou
 * (Quadro 10 pela zona, Quadro 11-A pela classe viária). A planilha de
 * enquadramento que bloqueia o ramo usa o motivo da própria regra.
 */
class JustificativaIndeferimentoBuilder
{
    public function __construct(private DecisionTextCatalog $textos) {}

    public function build(ResolvedViability $resolved): string
    {
        $paragrafos = [];

        foreach ($resolved->por_cnae as $item) {
            if (($item['tendencia'] ?? null) !== ResultadoViabilidade::NaoPermitido->value) {
                continue;
            }

            $consulta = $item['consulta'] ?? null;

            if (! $consulta instanceof ConsultaViabilidadeResult) {
                continue;
            }

            $paragrafos[] = 'CNAE '.$item['cnae_formatado'].': '.$this->justificativaDoCnae($consulta);
        }

        return implode("\n\n", $paragrafos);
    }

    private function justificativaDoCnae(ConsultaViabilidadeResult $consulta): string
    {
        $enquadramento = $consulta->enquadramento;

        if (($enquadramento->enquadramento['bloqueia'] ?? false) === true) {
            return (string) ($enquadramento->consolidado['motivo'] ?? '');
        }

        if (($enquadramento->quadro10['permissao'] ?? null) === Quadro10Permissao::Proibido->value) {
            $texto = $this->textos->render('justificativa.conclusao.nao_permitido', [
                ':zona' => $this->zona($consulta),
            ]);
            $observacao = $this->observacaoDaZona($enquadramento->quadro10['motivo'] ?? null);

            return $observacao === null ? $texto : $texto.' '.$observacao;
        }

        return $this->textos->render('justificativa.conclusao.nao_permitido_via', [
            ':classe_via' => $this->classeVia($consulta),
        ]);
    }

    private function zona(ConsultaViabilidadeResult $consulta): string
    {
        $nome = $consulta->territory?->zona['nome'] ?? null;

        return is_string($nome) && trim($nome) !== '' ? trim($nome) : 'informada';
    }

    private function classeVia(ConsultaViabilidadeResult $consulta): string
    {
        $via = $consulta->territory?->via ?? [];
        $classe = $via['propriedades']['CLASSE_VIA_LOUOS'] ?? $via['nome'] ?? null;

        return is_string($classe) && trim($classe) !== '' ? trim($classe) : 'informada';
    }

    /**
     * A observação (a)/(b) do Quadro 10 explica por que o uso, permitido na
     * zona em geral, é vedado neste endereço.
     */
    private function observacaoDaZona(mixed $motivo): ?string
    {
        foreach (['(a)', '(b)'] as $observacao) {
            $texto = $this->textos->render('louos.motivo.quadro10_observacao_fora_da_lista', [':observacao' => $observacao]);

            if ($motivo === $texto) {
                return rtrim($texto, '.').'.';
            }
        }

        return null;
    }
}
