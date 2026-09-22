<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ficha de vistoria do processo. A identificação (tipo, opened_at,
 * vistoriador) é gravada na abertura — nunca digitada. A localização é um
 * SNAPSHOT do endereço do processo no momento da abertura (o cadastro pode
 * mudar depois; a ficha preserva o que foi vistoriado). O polígono nasce do
 * property_polygon_geojson do processo e pode ser redesenhado/validado pelo
 * vistoriador (polygon_validated_at + área calculada). Concluída é imutável:
 * o parecer é a peça oficial (parecer obrigatório na conclusão).
 *
 * status/tipo/opened_at/concluded_at/polygon_* ficam FORA do fillable quando
 * escritos por fluxo próprio — o fillable cobre os campos que o vistoriador
 * preenche no rascunho/conclusão.
 */
#[Fillable([
    'viability_request_id',
    'ponto_referencia', 'logradouro_correto',
    'tipo_imovel', 'acessos', 'atividade_em_funcionamento',
    'complemento_tipo', 'complemento_numero', 'complemento_area_m2', 'area_total_m2',
    'vagas_veiculo_passeio', 'vagas_carga_descarga', 'patio_carga_descarga', 'area_embarque_desembarque',
    'area_terreno_m2', 'area_total_construida_m2', 'area_ocupada_atividade_m2', 'area_carga_descarga_m2',
    'pavimento_edificacao', 'pavimento_ocupado_atividade', 'recuo_m',
    'entorno_residencial_m', 'entorno_industrial_m', 'entorno_saude_m', 'entorno_comercial_m',
    'entorno_institucional_m', 'entorno_especial_m', 'entorno_educacional_m', 'entorno_misto_m', 'entorno_outros_m',
    'instalacoes_eletricas', 'instalacoes_hidrossanitarias', 'obras', 'alvara_numero',
    'quantidade_usuarios', 'num_salas_alunos', 'num_assentos', 'unidades_hospedagem', 'num_leitos',
    'equipamentos', 'equipamentos_outros', 'maquinas_motores', 'sons_ruidos', 'sons_ruidos_origem',
    'seg_extintores', 'seg_central_gas', 'seg_hidrantes', 'seg_outros',
    'observacoes', 'parecer', 'data_vistoria', 'contato_nome', 'contato_telefone',
])]
class Inspection extends Model
{
    use HasAuditoria;

    /** @use HasFactory<InspectionFactory> */
    use HasFactory;

    /**
     * Vocabulários fixos do formulário (opções do legado) — fonte única para
     * as regras de validação e para as opções enviadas à tela.
     *
     * @var list<string>
     */
    public const ACESSOS = ['comum', 'requerente', 'independente', 'terceiro'];

    /** @var list<string> */
    public const OBRAS = ['construcao', 'ampliacao', 'reforma', 'reparos_gerais'];

    /** @var list<string> */
    public const EQUIPAMENTOS = ['fogao_caseiro', 'fogao_lenha', 'fogao_industrial', 'forno_gas', 'churrasqueira', 'forno_eletrico', 'caldeira'];

    /** @var list<string> */
    public const SATISFACAO = ['satisfaz', 'nao_satisfaz'];

    /**
     * Rótulos pt-BR dos vocabulários, para a tela não hardcodar texto.
     *
     * @return array{acessos: array<string, string>, obras: array<string, string>, equipamentos: array<string, string>, satisfacao: array<string, string>}
     */
    public static function rotulos(): array
    {
        return [
            'acessos' => [
                'comum' => 'Comum',
                'requerente' => 'Requerente',
                'independente' => 'Independente',
                'terceiro' => 'Terceiro',
            ],
            'obras' => [
                'construcao' => 'Construção',
                'ampliacao' => 'Ampliação',
                'reforma' => 'Reforma',
                'reparos_gerais' => 'Reparos Gerais',
            ],
            'equipamentos' => [
                'fogao_caseiro' => 'Fogão Caseiro',
                'fogao_lenha' => 'Fogão a Lenha',
                'fogao_industrial' => 'Fogão Industrial',
                'forno_gas' => 'Forno a Gás',
                'churrasqueira' => 'Churrasqueira',
                'forno_eletrico' => 'Forno Elétrico',
                'caldeira' => 'Caldeira',
            ],
            'satisfacao' => [
                'satisfaz' => 'Satisfaz',
                'nao_satisfaz' => 'Não Satisfaz',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InspectionStatus::class,
            'tipo' => InspectionType::class,
            'opened_at' => 'datetime',
            'concluded_at' => 'datetime',
            'polygon_geojson' => 'array',
            'polygon_validated_at' => 'datetime',
            'polygon_area_m2' => 'decimal:2',
            'logradouro_correto' => 'boolean',
            'acessos' => 'array',
            'atividade_em_funcionamento' => 'boolean',
            'complemento_area_m2' => 'decimal:2',
            'area_total_m2' => 'decimal:2',
            'patio_carga_descarga' => 'boolean',
            'area_embarque_desembarque' => 'boolean',
            'area_terreno_m2' => 'decimal:2',
            'area_total_construida_m2' => 'decimal:2',
            'area_ocupada_atividade_m2' => 'decimal:2',
            'area_carga_descarga_m2' => 'decimal:2',
            'recuo_m' => 'decimal:2',
            'entorno_residencial_m' => 'decimal:2',
            'entorno_industrial_m' => 'decimal:2',
            'entorno_saude_m' => 'decimal:2',
            'entorno_comercial_m' => 'decimal:2',
            'entorno_institucional_m' => 'decimal:2',
            'entorno_especial_m' => 'decimal:2',
            'entorno_educacional_m' => 'decimal:2',
            'entorno_misto_m' => 'decimal:2',
            'entorno_outros_m' => 'decimal:2',
            'obras' => 'array',
            'equipamentos' => 'array',
            'maquinas_motores' => 'boolean',
            'sons_ruidos' => 'boolean',
            'seg_extintores' => 'boolean',
            'seg_central_gas' => 'boolean',
            'seg_hidrantes' => 'boolean',
            'data_vistoria' => 'date',
        ];
    }

    public function isConcluida(): bool
    {
        return $this->status->isConcluida();
    }

    /**
     * Processo ao qual a ficha pertence.
     *
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * Vistoriador responsável — gravado na abertura da ficha.
     *
     * @return BelongsTo<User, $this>
     */
    public function vistoriador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vistoriador_user_id');
    }

    /**
     * Anexos da vistoria (fotos/documentos coletados em campo).
     *
     * @return HasMany<InspectionAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(InspectionAttachment::class);
    }
}
