<?php

namespace App\Http\Resources;

use App\Models\Inspection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha de vistoria para a tela: identificação (tipo, abertura, vistoriador),
 * snapshot da localização, polígono e todos os campos preenchíveis. Datas em
 * ISO 8601; `editavel`=false quando concluída (a tela vira somente leitura).
 *
 * @mixin Inspection
 */
class InspectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'viability_request_id' => $this->viability_request_id,
            'tipo' => $this->tipo->value,
            'tipo_label' => $this->tipo->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            // Edição é do vistoriador que abriu a ficha, enquanto não concluída
            // — os demais usuários com a permissão leem (somente leitura).
            'editavel' => ! $this->isConcluida() && $request->user()?->id === $this->vistoriador_user_id,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'concluded_at' => $this->concluded_at?->toIso8601String(),
            'vistoriador_id' => $this->vistoriador_user_id,
            'vistoriador' => $this->whenLoaded('vistoriador', fn () => $this->vistoriador->name),

            // Localização (snapshot).
            'cod_logradouro' => $this->cod_logradouro,
            'logradouro' => $this->logradouro,
            'numero_metrico' => $this->numero_metrico,
            'bairro' => $this->bairro,
            'cep' => $this->cep,
            'ponto_referencia' => $this->ponto_referencia,
            'zona' => $this->zona,
            'via' => $this->via,
            'logradouro_correto' => $this->logradouro_correto,

            // Polígono.
            'polygon_geojson' => $this->polygon_geojson,
            'polygon_validated_at' => $this->polygon_validated_at?->toIso8601String(),
            'polygon_area_m2' => $this->polygon_area_m2 !== null ? (float) $this->polygon_area_m2 : null,

            // Dados do imóvel.
            'tipo_imovel' => $this->tipo_imovel,
            'acessos' => $this->acessos ?? [],
            'atividade_em_funcionamento' => $this->atividade_em_funcionamento,
            'complemento_tipo' => $this->complemento_tipo,
            'complemento_numero' => $this->complemento_numero,
            'complemento_area_m2' => $this->complemento_area_m2 !== null ? (float) $this->complemento_area_m2 : null,
            'area_total_m2' => $this->area_total_m2 !== null ? (float) $this->area_total_m2 : null,

            // Vagas.
            'vagas_veiculo_passeio' => $this->vagas_veiculo_passeio,
            'vagas_carga_descarga' => $this->vagas_carga_descarga,
            'patio_carga_descarga' => $this->patio_carga_descarga,
            'area_embarque_desembarque' => $this->area_embarque_desembarque,

            // Características.
            'area_terreno_m2' => $this->area_terreno_m2 !== null ? (float) $this->area_terreno_m2 : null,
            'area_total_construida_m2' => $this->area_total_construida_m2 !== null ? (float) $this->area_total_construida_m2 : null,
            'area_ocupada_atividade_m2' => $this->area_ocupada_atividade_m2 !== null ? (float) $this->area_ocupada_atividade_m2 : null,
            'area_carga_descarga_m2' => $this->area_carga_descarga_m2 !== null ? (float) $this->area_carga_descarga_m2 : null,
            'pavimento_edificacao' => $this->pavimento_edificacao,
            'pavimento_ocupado_atividade' => $this->pavimento_ocupado_atividade,
            'recuo_m' => $this->recuo_m !== null ? (float) $this->recuo_m : null,

            // Entorno.
            'entorno_residencial_m' => $this->entorno_residencial_m !== null ? (float) $this->entorno_residencial_m : null,
            'entorno_industrial_m' => $this->entorno_industrial_m !== null ? (float) $this->entorno_industrial_m : null,
            'entorno_saude_m' => $this->entorno_saude_m !== null ? (float) $this->entorno_saude_m : null,
            'entorno_comercial_m' => $this->entorno_comercial_m !== null ? (float) $this->entorno_comercial_m : null,
            'entorno_institucional_m' => $this->entorno_institucional_m !== null ? (float) $this->entorno_institucional_m : null,
            'entorno_especial_m' => $this->entorno_especial_m !== null ? (float) $this->entorno_especial_m : null,
            'entorno_educacional_m' => $this->entorno_educacional_m !== null ? (float) $this->entorno_educacional_m : null,
            'entorno_misto_m' => $this->entorno_misto_m !== null ? (float) $this->entorno_misto_m : null,
            'entorno_outros_m' => $this->entorno_outros_m !== null ? (float) $this->entorno_outros_m : null,

            // Infraestrutura.
            'instalacoes_eletricas' => $this->instalacoes_eletricas,
            'instalacoes_hidrossanitarias' => $this->instalacoes_hidrossanitarias,
            'obras' => $this->obras ?? [],
            'alvara_numero' => $this->alvara_numero,
            'quantidade_usuarios' => $this->quantidade_usuarios,
            'num_salas_alunos' => $this->num_salas_alunos,
            'num_assentos' => $this->num_assentos,
            'unidades_hospedagem' => $this->unidades_hospedagem,
            'num_leitos' => $this->num_leitos,

            // Controle ambiental.
            'equipamentos' => $this->equipamentos ?? [],
            'equipamentos_outros' => $this->equipamentos_outros,
            'maquinas_motores' => $this->maquinas_motores,
            'sons_ruidos' => $this->sons_ruidos,
            'sons_ruidos_origem' => $this->sons_ruidos_origem,

            // Segurança.
            'seg_extintores' => $this->seg_extintores,
            'seg_central_gas' => $this->seg_central_gas,
            'seg_hidrantes' => $this->seg_hidrantes,
            'seg_outros' => $this->seg_outros,

            // Conclusão.
            'observacoes' => $this->observacoes,
            'parecer' => $this->parecer,
            'data_vistoria' => $this->data_vistoria?->toDateString(),
            'contato_nome' => $this->contato_nome,
            'contato_telefone' => $this->contato_telefone,
        ];
    }
}
