<?php

namespace App\Services\Territorial;

use App\Models\RuleVersion;
use App\Models\TerritorialRule;

/**
 * Copia as regras territoriais (com CNAEs e endereços filhos) da versão
 * vigente para o rascunho/nova versão — espelha o LouosQuadroCopier dos
 * quadros LOUOS. Chave natural da regra: `codigo` (RT-01, RT-02-ITAIGARA…).
 */
class TerritorialRuleCopier
{
    /**
     * Cópia integral da versão de origem para o destino.
     */
    public function copy(?RuleVersion $from, RuleVersion $to): void
    {
        if ($from === null) {
            return;
        }

        TerritorialRule::query()
            ->where('rule_version_id', $from->getKey())
            ->with(['cnaes', 'addresses'])
            ->orderBy('id')
            ->each(fn (TerritorialRule $regra) => $this->copiarRegra($regra, $to));
    }

    /**
     * Copia uma regra (com os filhos) para a versão de destino.
     */
    public function copiarRegra(TerritorialRule $regra, RuleVersion $to): TerritorialRule
    {
        $nova = TerritorialRule::create([
            'rule_version_id' => $to->getKey(),
            'codigo' => $regra->codigo,
            'titulo' => $regra->titulo,
            'tipo' => $regra->tipo,
            'efeito' => $regra->efeito,
            'cod_log' => $regra->cod_log,
            'logradouro_nome' => $regra->logradouro_nome,
            'camada' => $regra->camada,
            'camada_filtro' => $regra->camada_filtro,
            'zona_alvo' => $regra->zona_alvo,
            'motivo_analise' => $regra->motivo_analise,
            'decision_text_id' => $regra->decision_text_id,
            'base_legal' => $regra->base_legal,
            'ativo' => $regra->ativo,
        ]);

        foreach ($regra->cnaes as $cnae) {
            $nova->cnaes()->create(['cnae' => $cnae->cnae]);
        }

        foreach ($regra->addresses as $endereco) {
            $nova->addresses()->create([
                'rotulo' => $endereco->rotulo,
                'cod_log' => $endereco->cod_log,
                'logradouro_nome' => $endereco->logradouro_nome,
                'numero_porta' => $endereco->numero_porta,
                'bairro' => $endereco->bairro,
            ]);
        }

        return $nova;
    }
}
