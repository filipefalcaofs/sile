<?php

namespace App\Services\Analise;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TllValor;
use App\Support\Settings;

/**
 * Cálculo do valor do DAM da TLL (HU-071 RN-004): o valor é a atividade de
 * maior valor correlacionado à TLL, acrescido da taxa de serviço, aplicando o
 * fator multiplicador (parâmetro `tll.fator_multiplicador`, HU-014) quando o
 * CNAE o exigir. Sem valor parametrizado para o exercício, degrada para
 * pendente (null) — nunca valor inventado (anti-fachada).
 */
class TllCalculoService
{
    /**
     * Calcula o valor do DAM do processo a partir dos CNAEs (cada item traz o
     * `codigo_tll` resolvido pela planilha e a flag `exige_fator_multiplicador`
     * do CNAE). Null quando nenhum CNAE tem valor parametrizado no exercício.
     *
     * @param  list<array<string, mixed>>  $itens
     */
    public function calcular(array $itens, int $exercicio): ?TllCalculo
    {
        $versaoVigente = RuleVersion::query()
            ->vigente(RuleDomain::TllValores)
            ->where('version', (string) $exercicio)
            ->exists();

        if (! $versaoVigente) {
            return null;
        }

        $melhor = null;

        foreach ($itens as $item) {
            $codigoTll = $item['codigo_tll'] ?? null;

            if (! is_string($codigoTll) || $codigoTll === '') {
                continue;
            }

            $tll = TllValor::query()->active()->paraExercicio($codigoTll, $exercicio)->first();

            if ($tll === null) {
                continue;
            }

            if ($melhor === null || (float) $tll->valor > (float) $melhor->valor) {
                $melhor = $tll;
            }
        }

        if ($melhor === null) {
            return null;
        }

        $valor = (float) $melhor->valor + (float) $melhor->taxa_servico;
        $fatorAplicado = false;

        $exigeFator = collect($itens)->first(
            fn (array $item): bool => ($item['codigo_tll'] ?? null) === $melhor->codigo_tll
                && (bool) ($item['exige_fator_multiplicador'] ?? false),
        ) !== null;

        if ($exigeFator) {
            $fator = (float) Settings::get('tll.fator_multiplicador', 1.0);

            if ($fator > 0 && $fator !== 1.0) {
                $valor *= $fator;
                $fatorAplicado = true;
            }
        }

        return new TllCalculo(
            valor: number_format($valor, 2, '.', ''),
            codigo_tll: $melhor->codigo_tll,
            valor_tll: (string) $melhor->valor,
            taxa_servico: (string) $melhor->taxa_servico,
            fator_aplicado: $fatorAplicado,
            exercicio: $exercicio,
        );
    }
}
