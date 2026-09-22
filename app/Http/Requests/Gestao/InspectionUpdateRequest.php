<?php

namespace App\Http\Requests\Gestao;

use App\Models\Inspection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rascunho da ficha de vistoria: todos os campos são opcionais (sometimes) —
 * o vistoriador salva parcial e retoma depois. A obrigatoriedade do parecer
 * só vale na conclusão (InspectionConcludeRequest). Os vocabulários fechados
 * (acessos, obras, equipamentos, satisfação) vêm das constantes do model —
 * fonte única compartilhada com a tela.
 */
class InspectionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $area = ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999'];
        $distancia = ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999'];
        $inteiro = ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999'];
        $booleano = ['sometimes', 'nullable', 'boolean'];
        $texto = ['sometimes', 'nullable', 'string', 'max:255'];

        return [
            // Localização — únicos editáveis da seção (o restante é snapshot).
            'ponto_referencia' => $texto,
            'logradouro_correto' => $booleano,

            // Dados do imóvel.
            'tipo_imovel' => ['sometimes', 'nullable', 'string', 'max:100'],
            'acessos' => ['sometimes', 'nullable', 'array'],
            'acessos.*' => ['string', Rule::in(Inspection::ACESSOS)],
            'atividade_em_funcionamento' => $booleano,
            'complemento_tipo' => ['sometimes', 'nullable', 'string', 'max:100'],
            'complemento_numero' => ['sometimes', 'nullable', 'string', 'max:50'],
            'complemento_area_m2' => $area,
            'area_total_m2' => $area,

            // Vagas de vistoria.
            'vagas_veiculo_passeio' => $inteiro,
            'vagas_carga_descarga' => $inteiro,
            'patio_carga_descarga' => $booleano,
            'area_embarque_desembarque' => $booleano,

            // Características do imóvel.
            'area_terreno_m2' => $area,
            'area_total_construida_m2' => $area,
            'area_ocupada_atividade_m2' => $area,
            'area_carga_descarga_m2' => $area,
            'pavimento_edificacao' => ['sometimes', 'nullable', 'string', 'max:50'],
            'pavimento_ocupado_atividade' => ['sometimes', 'nullable', 'string', 'max:50'],
            'recuo_m' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999'],

            // Entorno — distância em metro linear.
            'entorno_residencial_m' => $distancia,
            'entorno_industrial_m' => $distancia,
            'entorno_saude_m' => $distancia,
            'entorno_comercial_m' => $distancia,
            'entorno_institucional_m' => $distancia,
            'entorno_especial_m' => $distancia,
            'entorno_educacional_m' => $distancia,
            'entorno_misto_m' => $distancia,
            'entorno_outros_m' => $distancia,

            // Infraestrutura.
            'instalacoes_eletricas' => ['sometimes', 'nullable', Rule::in(Inspection::SATISFACAO)],
            'instalacoes_hidrossanitarias' => ['sometimes', 'nullable', Rule::in(Inspection::SATISFACAO)],
            'obras' => ['sometimes', 'nullable', 'array'],
            'obras.*' => ['string', Rule::in(Inspection::OBRAS)],
            'alvara_numero' => ['sometimes', 'nullable', 'string', 'max:50'],
            'quantidade_usuarios' => $inteiro,
            'num_salas_alunos' => $inteiro,
            'num_assentos' => $inteiro,
            'unidades_hospedagem' => $inteiro,
            'num_leitos' => $inteiro,

            // Controle ambiental.
            'equipamentos' => ['sometimes', 'nullable', 'array'],
            'equipamentos.*' => ['string', Rule::in(Inspection::EQUIPAMENTOS)],
            'equipamentos_outros' => $texto,
            'maquinas_motores' => $booleano,
            'sons_ruidos' => $booleano,
            'sons_ruidos_origem' => $texto,

            // Equipamentos de segurança.
            'seg_extintores' => $booleano,
            'seg_central_gas' => $booleano,
            'seg_hidrantes' => $booleano,
            'seg_outros' => $texto,

            // Conclusão.
            'observacoes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'parecer' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'data_vistoria' => ['sometimes', 'nullable', 'date'],
            'contato_nome' => $texto,
            'contato_telefone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'acessos.*.in' => 'Opção de acesso inválida.',
            'obras.*.in' => 'Tipo de obra inválido.',
            'equipamentos.*.in' => 'Equipamento inválido.',
            'instalacoes_eletricas.in' => 'Informe se as instalações elétricas satisfazem ou não.',
            'instalacoes_hidrossanitarias.in' => 'Informe se as instalações hidrossanitárias satisfazem ou não.',
            '*.numeric' => 'O campo :attribute deve ser numérico.',
            '*.integer' => 'O campo :attribute deve ser um número inteiro.',
            '*.min' => 'O campo :attribute não pode ser negativo.',
        ];
    }
}
