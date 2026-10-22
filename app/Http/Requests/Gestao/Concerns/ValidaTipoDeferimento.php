<?php

namespace App\Http\Requests\Gestao\Concerns;

use App\Enums\TvlKind;
use App\Models\ViabilityRequest;
use App\Services\Analise\ValidadeDeferimento;
use Illuminate\Validation\Rule;

/**
 * Tipo do deferimento e validade na conclusão (SEDUR, 20/10/2026): Deferido
 * pede o tipo; Provisório e Pré-operacional pedem a validade (6, 12 ou 24
 * meses ou uma data personalizada). Renovação fica de fora: tem o diálogo próprio.
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
                Rule::in(['6', '12', '24', ValidadeDeferimento::PERSONALIZADO, 6, 12, 24]),
            ],
            'validade_data' => [
                Rule::requiredIf(fn (): bool => $this->pedeTipo()
                    && $this->input('validade_meses') === ValidadeDeferimento::PERSONALIZADO
                    && in_array($this->input('tipo'), [TvlKind::Provisoria->value, TvlKind::PreOperacional->value], true)),
                'nullable',
                'date_format:Y-m-d',
                'after:today',
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
            'validade_meses.in' => 'Escolha a validade entre 6 meses, 1 ano, 2 anos ou Personalizado.',
            'validade_data.required' => 'Informe a data da validade.',
            'validade_data.date_format' => 'Informe a data da validade no formato válido.',
            'validade_data.after' => 'A data da validade precisa ser posterior a hoje.',
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

        return ValidadeDeferimento::de($this->validated('tipo'), $this->validated('validade_meses'), $this->validated('validade_data'));
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
