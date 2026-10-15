<?php

namespace App\Services\Analise;

use App\Enums\Quadro10Permissao;

/**
 * Checklist ilustrativo dos quadros da LOUOS por CNAE na ficha de análise:
 * cada quadro vira uma linha com estado (permitido / não permitido /
 * condicionado / pendente) derivado do que o motor GRAVOU no snapshot —
 * projeção de leitura, nunca recomputa nem inventa o veredito.
 *
 * A interpretação do Quadro 11-A espelha a matriz oficial (Não veda, R vai à
 * CNLU, Sim puro não condiciona) que o LouosEnquadramentoService aplica.
 */
final class QuadrosFichaChecklist
{
    /**
     * @param  array<string, mixed>  $consulta  Item `consulta` do engine_snapshot da ficha.
     * @return list<array{key: string, label: string, estado: string, detalhe: string}>
     */
    /**
     * Anexa o checklist a cada CNAE da decisão expresso, lendo só o
     * decision_trace já gravado. Sem o bloco daquele CNAE, a lista fica vazia:
     * a ficha não inventa pendência.
     *
     * @param  list<array<string, mixed>>  $perCnae
     * @param  list<array<string, mixed>>  $decisionTrace
     * @return list<array<string, mixed>>
     */
    public function anexarAoPerCnae(array $perCnae, array $decisionTrace): array
    {
        return array_values(array_map(function (array $item) use ($decisionTrace): array {
            $bloco = $this->blocoDoCnae($decisionTrace, (string) ($item['cnae'] ?? ''));
            $item['quadros'] = $bloco === null ? [] : $this->para($this->consultaDoBloco($bloco));

            return $item;
        }, $perCnae));
    }

    public function para(array $consulta): array
    {
        // consulta.enquadramento é o EnquadramentoResult::toArray(): tem o uso
        // (enquadramento.grupo), os quadros (quadro10/quadro11a) e o consolidado.
        $resultado = is_array($consulta['enquadramento'] ?? null) ? $consulta['enquadramento'] : [];
        $uso = is_array($resultado['enquadramento'] ?? null) ? $resultado['enquadramento'] : [];
        $territorio = is_array($consulta['territorio'] ?? null) ? $consulta['territorio'] : [];

        $grupo = $this->texto($uso['subgrupo'] ?? null) ?? $this->texto($uso['grupo'] ?? null);
        $zona = $this->texto($territorio['zona']['nome'] ?? null);

        return [
            $this->quadro10(is_array($resultado['quadro10'] ?? null) ? $resultado['quadro10'] : [], $grupo, $zona),
            $this->quadro11a(is_array($resultado['quadro11a'] ?? null) ? $resultado['quadro11a'] : [], $grupo),
        ];
    }

    /**
     * @param  array<string, mixed>  $quadro
     * @return array{key: string, label: string, estado: string, detalhe: string}
     */
    private function quadro10(array $quadro, ?string $grupo, ?string $zona): array
    {
        $label = 'Quadro 10 — Permissão na zona';
        $alvo = trim(($grupo ?? 'o grupo').($zona !== null ? ' na zona '.$zona : ''));

        if (($quadro['status'] ?? null) !== 'identificado') {
            return $this->item('quadro10', $label, 'pendente', 'Permissão por zona não disponível na base oficial.');
        }

        return match ($quadro['permissao'] ?? null) {
            Quadro10Permissao::Permitido->value => $this->item('quadro10', $label, 'permitido', ucfirst($alvo).' permitido.'),
            Quadro10Permissao::Proibido->value => $this->item('quadro10', $label, 'nao_permitido', ucfirst($alvo).' proibido.'),
            Quadro10Permissao::PermitidoCondicionado->value => $this->item('quadro10', $label, 'condicionado', ucfirst($alvo).' permitido com condições.'),
            default => $this->item('quadro10', $label, 'pendente', 'Permissão por zona não disponível na base oficial.'),
        };
    }

    /**
     * @param  array<string, mixed>  $quadro
     * @return array{key: string, label: string, estado: string, detalhe: string}
     */
    private function quadro11a(array $quadro, ?string $grupo): array
    {
        $label = 'Quadro 11-A — Condições pela via';
        $classeVia = $this->texto($quadro['classe_via'] ?? null);
        $alvo = trim(($grupo ?? 'o grupo').($classeVia !== null ? ' na classe viária '.$classeVia : ''));

        if (($quadro['status'] ?? null) !== 'identificado') {
            return $this->item('quadro11a', $label, 'pendente', 'Condições pela via não disponíveis na base oficial.');
        }

        $tokens = $this->tokensVia($quadro['condicoes'] ?? []);

        if (in_array('nao', $tokens, true)) {
            return $this->item('quadro11a', $label, 'nao_permitido', 'Uso vedado para '.$alvo.'.');
        }

        if (in_array('r', $tokens, true)) {
            return $this->item('quadro11a', $label, 'pendente', 'Encaminhado à CNLU para '.$alvo.'.');
        }

        if ($tokens === ['sim']) {
            return $this->item('quadro11a', $label, 'permitido', 'Sem vedação para '.$alvo.'.');
        }

        return $this->item('quadro11a', $label, 'condicionado', 'Condições de instalação para '.$alvo.'.');
    }

    /**
     * Normaliza as condições do Quadro 11-A em tokens canônicos (nao/r/sim),
     * espelhando a leitura do motor. Textos livres não viram token.
     *
     * @return list<string>
     */
    private function tokensVia(mixed $condicoes): array
    {
        if (! is_array($condicoes)) {
            return [];
        }

        $out = [];

        foreach ($condicoes as $item) {
            if (! is_string($item)) {
                continue;
            }

            $norm = strtr(mb_strtolower(trim($item)), ['ã' => 'a', 'á' => 'a']);

            if (in_array($norm, ['nao', 'r', 'sim'], true)) {
                $out[] = $norm;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array{key: string, label: string, estado: string, detalhe: string}
     */
    private function item(string $key, string $label, string $estado, string $detalhe): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'estado' => $estado,
            'detalhe' => $detalhe,
        ];
    }

    private function texto(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $decisionTrace
     * @return array<string, mixed>|null
     */
    private function blocoDoCnae(array $decisionTrace, string $cnae): ?array
    {
        $digitos = preg_replace('/\D/', '', $cnae) ?? '';

        foreach ($decisionTrace as $bloco) {
            if (! is_array($bloco)) {
                continue;
            }

            $doBloco = preg_replace('/\D/', '', (string) ($bloco['cnae'] ?? '')) ?? '';

            if ($digitos !== '' && $digitos === $doBloco) {
                return $bloco;
            }
        }

        return null;
    }

    /**
     * Remonta o `consulta` que o checklist já sabe ler, a partir dos passos
     * gravados (enquadramento, Quadro 10 e Quadro 11-A). Passo não registrado
     * vira quadro vazio e, portanto, pendente.
     *
     * @param  array<string, mixed>  $bloco
     * @return array<string, mixed>
     */
    private function consultaDoBloco(array $bloco): array
    {
        $porPasso = [];

        foreach (is_array($bloco['passos'] ?? null) ? $bloco['passos'] : [] as $passo) {
            if (is_array($passo) && is_string($passo['passo'] ?? null)) {
                $porPasso[$passo['passo']] = $passo;
            }
        }

        return [
            'enquadramento' => [
                'enquadramento' => $this->resultadoDoPasso($porPasso['louos.enquadramento'] ?? null),
                'quadro10' => $this->resultadoDoPasso($porPasso['louos.quadro10'] ?? null),
                'quadro11a' => $this->resultadoDoPasso($porPasso['louos.quadro11a'] ?? null),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $passo
     * @return array<string, mixed>
     */
    private function resultadoDoPasso(?array $passo): array
    {
        if ($passo === null || ($passo['registrado'] ?? false) !== true) {
            return [];
        }

        return is_array($passo['resultado_parcial'] ?? null) ? $passo['resultado_parcial'] : [];
    }
}
