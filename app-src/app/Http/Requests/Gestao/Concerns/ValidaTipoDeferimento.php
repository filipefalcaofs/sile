<?php

namespace App\Http\Requests\Gestao\Concerns;

use App\Enums\TvlKind;
use App\Models\ViabilityRequest;
use App\Services\Analise\ValidadeDeferimento;
use Illuminate\Validation\Rule;

/**
 * Tipo do deferimento e validade na conclusão (SEDUR, 20/10/2026): Deferido
 * pede o tipo; Provisório e Pré-operacional pedem a validade (6, 12 ou 24
 * meses). Renovação fica de fora: tem o diálogo próprio.
 */
trait ValidaTipoDeferimento
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function regrasTipoDeferimento(): array
    {
        return [
            'tipo' => [
                Rule::requiredIf(fn (): bool => $this->pedeTipo()),
                'nullable',
                Rule::enum(TvlKind::class),
            ],
            'validade_meses' => [
                Rule::requiredIf(fn (): bool => $this->pedeTipo()
                    && in_array($this->input('tipo'), [TvlKind::Provisoria->value, TvlKind::PreOperacional->value], true)),
                'nullable',
                'integer',
                Rule::in([6, 12, 24]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensagensTipoDeferimento(): array
    {
        return [
            'tipo.required' => 'Escolha o tipo do deferimento.',
            'validade_meses.required' => 'Escolha a data da validade.',
            'validade_meses.in' => 'Escolha a validade entre 6 meses, 1 ano e 2 anos.',
        ];
    }

    /**
     * Validade escolhida, só quando o resultado é Deferido.
     */
    public function validadeDeferimento(): ?ValidadeDeferimento
    {
        if ($this->validated('resultado') !== 'deferido') {
            return null;
        }

        return ValidadeDeferimento::de($this->validated('tipo'), $this->validated('validade_meses'));
    }

    /**
     * Tipo e validade só valem no deferimento fora da renovação.
     */
    private function pedeTipo(): bool
    {
        return $this->input('resultado') === 'deferido' && ! $this->ehRenovacao();
    }

    private function ehRenovacao(): bool
    {
        $processo = $this->route('viabilityRequest');

        return $processo instanceof ViabilityRequest && $processo->isRenewal();
    }
}
