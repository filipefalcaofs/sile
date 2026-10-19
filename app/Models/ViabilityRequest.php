<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\AnalysisCategory;
use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\IntencaoAtividade;
use App\Enums\PoligonoFonte;
use App\Enums\PropertyLocationSource;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Enums\VirtualOfficeIntent;
use App\Enums\ZonaFonte;
use App\Services\Tratamento\RespostaPlanilha;
use Database\Factories\ViabilityRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Solicitação de viabilidade (EP08) — aggregate root com o imóvel embutido 1:1.
 * Auditoria automática via HasAuditoria (RN-002). protocol_number e status NÃO
 * são fillable: o número vem do ProtocolNumberGenerator e o status só muda pela
 * ViabilityRequestStateMachine (que audita a transição). A geometry derivada
 * property_polygon (só pgsql) também fica fora do fillable — a fonte é o jsonb
 * property_polygon_geojson, e a derivada é gravada via ST_* no plano 08-06.
 *
 * As colunas da análise técnica (EP10 — sector_id, assigned_user_id,
 * assigned_at, analysis_category, in_fine_mesh, analysis_stage,
 * analysis_stage_started_at, analysis_due_at) também ficam FORA do fillable:
 * são escritas pelos serviços de distribuição (HU-080/081) e de SLA (HU-144),
 * nunca pelo cidadão. O território materializado (zona_codigo, zona_fonte,
 * bairro_oficial, cod_log, property_point_lat/lng, property_location_source,
 * address_polygon_geojson, semap_use_category, semap_construction_standard)
 * idem: é escrito
 * pelos serviços de localização a partir da identificação espacial ou do
 * Cadastro Imobiliário, nunca pelo cidadão. cod_log é o CODLOG oficial do
 * logradouro — casamento das regras territoriais por endereço (RT-01,
 * allow-list de Itaigara, allow-lists de via do Quadro 10). O ponto gravado
 * só entra quando não há polígono; a origem diz de qual fonte ele veio.
 */
#[Fillable([
    'origin',
    'service_type_id', 'renews_viability_request_id', 'company_id', 'requester_user_id', 'created_by_user_id', 'assisted_attendance_id',
    'used_area_m2', 'property_registration',
    'address_street', 'address_number', 'address_complement', 'address_neighborhood', 'address_zip', 'address_reference',
    'property_polygon_geojson',
    'is_virtual_office', 'wants_virtual_office_hq', 'wants_virtual_office_tenant', 'confirma_perda_condicao_sede', 'is_public_area', 'has_independent_access',
    'simulation_snapshot', 'simulation_rules_versions', 'simulation_resultado', 'simulated_at',
    'applicant_proceeded_despite', 'contingency_reason', 'external_reference', 'regin_justificativa',
    'origem_declarada',
])]
class ViabilityRequest extends Model
{
    use HasAuditoria;

    /** @use HasFactory<ViabilityRequestFactory> */
    use HasFactory;

    /**
     * Respostas da planilha de tratamento (número da pergunta → sim/não).
     * Ainda não persistidas — o resolver lê o que o fluxo/teste informar.
     *
     * @var array<int, bool|int|list<int>>
     */
    public array $respostasTratamento = [];

    /**
     * Respostas da planilha para um CNAE: primeiro as informadas em memória,
     * senão as gravadas no snapshot da simulação REGIN.
     *
     * @return array<int, bool|int|list<int>>
     */
    public function respostasDaPlanilha(?string $cnae = null): array
    {
        $respostas = $this->respostasDaPlanilhaSemSede($cnae);

        // Pergunta 4 do CNAE gatilho (sede de escritório virtual) é respondida no passo do imóvel.
        if ($cnae !== null
            && ! array_key_exists(4, $respostas)
            && preg_replace('/\D/', '', $cnae) === preg_replace('/\D/', '', (string) config('viabiliza.analise.escritorio_virtual.cnae_gatilho_sede'))
            && $this->wants_virtual_office_hq !== null) {
            $respostas[4] = (bool) $this->wants_virtual_office_hq;
        }

        return $respostas;
    }

    /**
     * @return array<int, bool|int|list<int>>
     */
    private function respostasDaPlanilhaSemSede(?string $cnae): array
    {
        if ($this->respostasTratamento !== []) {
            return $this->respostasTratamento;
        }

        $snapshot = is_array($this->simulation_snapshot) ? $this->simulation_snapshot : [];
        $porCnae = $snapshot['respostas_tratamento_por_cnae'] ?? [];

        if ($cnae !== null && is_array($porCnae)) {
            $digitos = (string) preg_replace('/\D/', '', $cnae);
            $respostas = $porCnae[$digitos] ?? $porCnae[$cnae] ?? null;

            if (is_array($respostas)) {
                return array_combine(
                    array_map(intval(...), array_keys($respostas)),
                    array_map(
                        fn (mixed $valor): bool|int|array => RespostaPlanilha::de($valor)->paraArmazenar(),
                        array_values($respostas),
                    ),
                ) ?: [];
            }
        }

        return is_array($snapshot['respostas_tratamento'] ?? null) ? $snapshot['respostas_tratamento'] : [];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ViabilityRequestStatus::class,
            'origin' => ViabilityRequestOrigin::class,
            'property_polygon_geojson' => 'array',
            'address_polygon_geojson' => 'array',
            'origem_declarada' => 'array',
            'address_polygon_consulted_at' => 'datetime',
            'address_polygon_source' => PoligonoFonte::class,
            'zona_fonte' => ZonaFonte::class,
            'property_point_lat' => 'float',
            'property_point_lng' => 'float',
            'property_location_source' => PropertyLocationSource::class,
            'camadas_incidentes' => 'array',
            'simulation_snapshot' => 'array',
            'simulation_rules_versions' => 'array',
            'used_area_m2' => 'decimal:2',
            'is_virtual_office' => 'boolean',
            'wants_virtual_office_hq' => 'boolean',
            'wants_virtual_office_tenant' => 'boolean',
            'confirma_perda_condicao_sede' => 'boolean',
            'is_public_area' => 'boolean',
            'has_independent_access' => 'boolean',
            'applicant_proceeded_despite' => 'boolean',
            'simulated_at' => 'datetime',
            'protocoled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'bap_due_at' => 'datetime',
            'bap_linked_at' => 'datetime',
            // Colunas da análise técnica (EP10) — FORA do fillable (escritas por
            // serviços de distribuição/SLA, nunca pelo cidadão).
            'analysis_category' => AnalysisCategory::class,
            'analysis_stage' => AnalysisStage::class,
            'analysis_status' => AnalysisStatus::class,
            'in_fine_mesh' => 'boolean',
            'assigned_at' => 'datetime',
            'analysis_stage_started_at' => 'datetime',
            'analysis_due_at' => 'datetime',
            'viabilidade_horas_uteis' => 'float',
        ];
    }

    /**
     * Ponto (lat, lng) usado pelos motores. O polígono desenhado manda: o
     * centroide do anel exterior. Sem polígono, vale o ponto gravado (Cadastro
     * Imobiliário ou catálogo da simulação). Sem os dois, o centroide do lote
     * encontrado no endereçamento (address_polygon_geojson). Sem nenhum, não há
     * ponto.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function pontoDoImovel(): ?array
    {
        return $this->pontoGravadoOuDesenhado() ?? $this->centroideDoPoligono($this->address_polygon_geojson);
    }

    /**
     * Ponto que o requerente desenhou ou que a inscrição/simulação gravou, sem
     * o lote do endereçamento. É o critério de "já localizado pela inscrição":
     * o lote achado depois não substitui a busca do ponto no Cadastro.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function pontoGravadoOuDesenhado(): ?array
    {
        $centroide = $this->centroideDoPoligono($this->property_polygon_geojson);

        if ($centroide !== null) {
            return $centroide;
        }

        if ($this->property_point_lat === null || $this->property_point_lng === null) {
            return null;
        }

        return [
            'lat' => (float) $this->property_point_lat,
            'lng' => (float) $this->property_point_lng,
        ];
    }

    /**
     * Centroide pela média dos vértices do anel exterior, sem o vértice de
     * fechamento. Mesmo cálculo que o resolver de viabilidade usava. Em lote
     * côncavo a média pode cair fora do polígono.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function centroideDoPoligono(mixed $geojson): ?array
    {
        $ring = is_array($geojson) ? ($geojson['coordinates'][0] ?? null) : null;

        if (! is_array($ring) || $ring === []) {
            return null;
        }

        if (count($ring) > 1 && $ring[0] === $ring[count($ring) - 1]) {
            array_pop($ring);
        }

        $lngs = array_map(static fn (array $point): float => (float) $point[0], $ring);
        $lats = array_map(static fn (array $point): float => (float) $point[1], $ring);

        return [
            'lat' => array_sum($lats) / count($lats),
            'lng' => array_sum($lngs) / count($lngs),
        ];
    }

    /**
     * Invalida a simulação orientativa (HU-063 RN-005): mudança de área, CNAE
     * ou imóvel zera o snapshot — o consumidor (08-06/08-07) chama este método
     * para forçar nova simulação. Escrita única aqui evita conflito entre os
     * planos paralelos da fase.
     */
    public function markSimulationStale(): void
    {
        $this->forceFill([
            'simulation_snapshot' => null,
            'simulation_rules_versions' => null,
            'simulation_resultado' => null,
            'simulated_at' => null,
        ])->save();
    }

    /**
     * Intencao de escritorio virtual derivada das duas respostas (RN-EV-01).
     * A pergunta geral manda: so quando ela e "Nao" a vinculada e exibida.
     * Quem caracteriza a sede e a pergunta vinculada (CNAE 8211-3/00 + "Sim"),
     * nao a geral (SEDUR 2026-08-31): por isso a condicao e `!== true`, e nao
     * `=== false`. `null` e dado legado — a coluna da pergunta geral so passou
     * a existir na migration de 2026-08-28, entao toda solicitacao anterior
     * tem `tenant = null` e nao pode ser reclassificada como "nenhum" so por
     * isso.
     */
    public function virtualOfficeIntent(): VirtualOfficeIntent
    {
        if ($this->wants_virtual_office_tenant === true) {
            return VirtualOfficeIntent::Abrigado;
        }

        if ($this->wants_virtual_office_tenant !== true && $this->wants_virtual_office_hq) {
            return VirtualOfficeIntent::Sede;
        }

        return VirtualOfficeIntent::Nenhum;
    }

    /**
     * Status de tramitação como a retaguarda o lê: o rótulo da etapa, com o setor
     * de destino quando o processo foi encaminhado e o resultado quando a análise
     * foi concluída. Null enquanto o processo não entrou no eixo operacional.
     */
    public function rotuloStatusTramitacao(): ?string
    {
        $status = $this->analysis_status;

        if ($status === null) {
            return null;
        }

        if ($status === AnalysisStatus::Encaminhado) {
            $setor = $this->sector?->name;

            return $setor === null ? $status->label() : "{$status->label()} {$setor}";
        }

        if ($status === AnalysisStatus::ParaConclusao) {
            $destinatario = $this->conclusaoEncaminhadaPara?->name;

            return $destinatario === null ? $status->label() : "{$status->label()} – {$destinatario}";
        }

        if ($status === AnalysisStatus::AnaliseConcluida) {
            $resultado = match ($this->status) {
                ViabilityRequestStatus::Deferida => 'Deferido',
                ViabilityRequestStatus::Indeferida => 'Indeferido',
                ViabilityRequestStatus::Cassado => 'Cassado',
                ViabilityRequestStatus::Revogado => 'Revogado',
                ViabilityRequestStatus::Desativado => 'Desativado',
                default => null,
            };

            return $resultado === null ? $status->label() : "{$status->label()} – {$resultado}";
        }

        return $status->label();
    }

    /**
     * Abrigado de escritório virtual: declarou a intenção no portal ou o
     * produto já foi decidido como abrigado.
     */
    public function ehAbrigadoDeEscritorioVirtual(): bool
    {
        return $this->wants_virtual_office_tenant === true
            || $this->decision?->is_virtual_office_tenant === true;
    }

    /**
     * Tipo de serviço (HU-061 RN-005).
     *
     * @return BelongsTo<ViabilityServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ViabilityServiceType::class, 'service_type_id');
    }

    /**
     * Solicitação de origem que esta renovação prorroga. Nula fora da renovação.
     *
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function renews(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renews_viability_request_id');
    }

    public function isRenewal(): bool
    {
        return $this->renews_viability_request_id !== null;
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Beneficiário da solicitação.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /**
     * Ator real que criou a solicitação ("em nome de" — HU-150).
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /**
     * Atendimento presencial que originou a solicitação, quando aberta no balcão
     * "em nome de" o cidadão (HU-150 RN-005 — dimensão balcão dos relatórios).
     *
     * @return BelongsTo<AssistedAttendance, $this>
     */
    public function assistedAttendance(): BelongsTo
    {
        return $this->belongsTo(AssistedAttendance::class);
    }

    /**
     * CNAEs da solicitação (principal e complementares), espelhando company_cnae.
     *
     * @return BelongsToMany<Cnae, $this>
     */
    public function cnaes(): BelongsToMany
    {
        return $this->belongsToMany(Cnae::class, 'viability_request_cnaes')->withPivot('is_primary', 'intencao')->withTimestamps();
    }

    /**
     * CNAE principal (exatamente um, garantido na aplicação).
     *
     * @return BelongsToMany<Cnae, $this>
     */
    public function primaryCnae(): BelongsToMany
    {
        return $this->cnaes()->wherePivot('is_primary', true);
    }

    /**
     * CNAEs marcados para saída na alteração de atividade (RN-AA-05b).
     *
     * @return Collection<int, Cnae>
     */
    public function cnaesParaExcluir(): Collection
    {
        return $this->cnaes()->wherePivot('intencao', IntencaoAtividade::Excluir->value)->get();
    }

    /**
     * Verdadeiro quando a solicitação lista ao menos um CNAE e todos estão
     * marcados para exclusão. Exige a linha antes de avaliar "todos": sem
     * isso, uma solicitação sem CNAE nenhum passaria pelo quantificador
     * universal sobre conjunto vazio, que em PHP é verdadeiro.
     */
    public function exclusivamenteExclusao(): bool
    {
        $intencoes = $this->cnaes()->pluck('viability_request_cnaes.intencao');

        if ($intencoes->isEmpty()) {
            return false;
        }

        return $intencoes->every(fn (?string $intencao) => $intencao === IntencaoAtividade::Excluir->value);
    }

    /**
     * Anexos (HU-066).
     *
     * @return HasMany<ViabilityRequestDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ViabilityRequestDocument::class);
    }

    /**
     * Eventos do protocolo enviados pela JUCEB (inscrição, alteração, regularização).
     *
     * @return HasMany<ViabilityRequestEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(ViabilityRequestEvento::class)->orderBy('id');
    }

    /**
     * Etapas do motor que dependem de serviço de terceiro.
     *
     * @return HasMany<ViabilityRequestEtapa, $this>
     */
    public function etapas(): HasMany
    {
        return $this->hasMany(ViabilityRequestEtapa::class);
    }

    /**
     * Transições de estado — fonte da timeline (HU-069), mais recente primeiro.
     *
     * @return HasMany<ViabilityRequestTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(ViabilityRequestTransition::class)->latest();
    }

    /**
     * Timeline interna do eixo operacional da análise (AnalysisStatus).
     *
     * @return HasMany<AnalysisStatusTransition, $this>
     */
    public function analysisStatusTransitions(): HasMany
    {
        return $this->hasMany(AnalysisStatusTransition::class, 'viability_request_id');
    }

    /**
     * Decisão do fluxo expresso (EP09) — 1:1 imutável, existe só após
     * deferir/indeferir. bap_due_at/bap_linked_at (HU-134) ficam fora do
     * fillable (como protocol_number/status): são gravados via forceFill pela
     * rotina do BAP quando o Regin entrar (Fase 13).
     *
     * @return HasOne<ViabilityDecision, $this>
     */
    public function decision(): HasOne
    {
        return $this->hasOne(ViabilityDecision::class);
    }

    /**
     * Setor (caixa) que recebeu o processo na distribuição (HU-080). sector_id
     * fica fora do fillable — gravado pelo serviço de distribuição.
     *
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * Analista que assumiu o processo (HU-081). assigned_user_id fica fora do
     * fillable — gravado ao assumir.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Destinatário opcional da conclusão, informado pelo analista ao enviar o
     * processo para conclusão. Quem analisou continua em assignedTo.
     *
     * @return BelongsTo<User, $this>
     */
    public function conclusaoEncaminhadaPara(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conclusao_encaminhada_para_user_id');
    }

    /**
     * Fichas de análise versionadas (HU-135/140) — uma linha por revisão.
     *
     * @return HasMany<AnalysisRecord, $this>
     */
    public function analysisRecords(): HasMany
    {
        return $this->hasMany(AnalysisRecord::class);
    }

    /**
     * Ficha de vistoria do processo (a mais recente, se houver mais de uma
     * por reabertura futura). Alimenta a consulta de vistorias.
     *
     * @return HasOne<Inspection, $this>
     */
    public function inspection(): HasOne
    {
        return $this->hasOne(Inspection::class)->latestOfMany();
    }

    /**
     * Quedas do fluxo expresso que originaram o encaminhamento à análise.
     *
     * @return HasMany<ExpressoQueda, $this>
     */
    public function expressoQuedas(): HasMany
    {
        return $this->hasMany(ExpressoQueda::class);
    }

    /**
     * Última transição para em_analise — motivo do encaminhamento quando o
     * expresso não emitiu decisão (zona pendente, gatilho, toggle).
     *
     * @return HasOne<ViabilityRequestTransition, $this>
     */
    public function encaminhamentoAnalise(): HasOne
    {
        return $this->hasOne(ViabilityRequestTransition::class)
            ->where('to_status', ViabilityRequestStatus::EmAnalise)
            ->latestOfMany();
    }

    /**
     * Ficha vigente = a de MAIOR revisão (RN-003 — versionamento). A finalizada
     * é imutável; recalcular gera nova revisão, que passa a ser a current.
     *
     * @return HasOne<AnalysisRecord, $this>
     */
    public function currentAnalysisRecord(): HasOne
    {
        return $this->hasOne(AnalysisRecord::class)->latestOfMany('revision');
    }

    /**
     * Pendências abertas/respondidas/expiradas do processo (HU-083/084).
     *
     * @return HasMany<AnalysisPendency, $this>
     */
    public function pendencies(): HasMany
    {
        return $this->hasMany(AnalysisPendency::class);
    }

    /**
     * Encaminhamentos à malha fina (HU-136) — ortogonais ao status, repetíveis.
     *
     * @return HasMany<FineMeshReferral, $this>
     */
    public function fineMeshReferrals(): HasMany
    {
        return $this->hasMany(FineMeshReferral::class);
    }
}
