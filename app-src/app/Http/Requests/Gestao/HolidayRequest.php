<?php

namespace App\Http\Requests\Gestao;

use App\Models\Holiday;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Validação do CRUD de feriados (HU-137). Serve store e update: a data é única
 * (não há feriado duplicado) e, no update, o unique ignora o próprio registro.
 * A autorização é o middleware permission:manter-parametros da rota.
 *
 * A data é normalizada para o formato em que o cast `date` do model a grava
 * (Y-m-d H:i:s, meia-noite) ANTES da validação, para que a regra unique compare
 * o MESMO valor armazenado (evita o falso negativo do match data-só × datetime).
 */
class HolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza a data (para casar com o armazenamento do cast) e os booleanos
     * (feriado novo nasce ativo; não recorrente por padrão).
     */
    protected function prepareForValidation(): void
    {
        $merge = [
            'recurring_annually' => $this->boolean('recurring_annually'),
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ];

        $date = $this->input('date');

        if (is_string($date) && trim($date) !== '') {
            try {
                $merge['date'] = Carbon::parse($date)->startOfDay()->format('Y-m-d H:i:s');
            } catch (Throwable) {
                // Valor cru permanece — a regra date: o rejeita honestamente.
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $holiday = $this->route('holiday');
        $holidayId = $holiday instanceof Holiday ? $holiday->getKey() : $holiday;

        return [
            'date' => ['required', 'date', Rule::unique('holidays', 'date')->ignore($holidayId)],
            'name' => ['required', 'string', 'max:255'],
            'recurring_annually' => ['boolean'],
            'active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => 'data',
            'name' => 'nome',
            'recurring_annually' => 'recorrência anual',
            'active' => 'situação',
        ];
    }
}
