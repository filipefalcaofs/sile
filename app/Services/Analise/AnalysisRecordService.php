<?php

namespace App\Services\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\AnalysisStatus;
use App\Models\AnalysisDivergence;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Ficha de análise (HU-126) — o coração da análise humana. Orquestra a abertura
 * da revisão vigente, o autosave do rascunho (RN-008), a finalização imutável
 * (RN-003) com o registro das divergências analista×motor (HU-130 RN-002) e a
 * criação de uma nova revisão para reedição/recálculo após a finalização.
 *
 * A revisão é versionada e append-only: a finalizada NUNCA é alterada — recalcular
 * ou reeditar gera uma nova linha (revision+1). O dado bruto do motor vive no
 * engine_snapshot; o autosave só toca os campos do rascunho preservando a sugestão
 * do motor (status_sugerido), que é o insumo da divergência na finalização.
 * O Motivo de Análise (`analysis_reasons`) é do sistema (queda do expresso) —
 * o autosave ignora qualquer valor enviado pelo cliente.
 *
 * Cada ficha pertence a quem a preencheu (HU.183). Rascunho sem dono é base: a
 * base do motor, que serve a qualquer analista, ou a cópia de conclusão, que só
 * serve a quem conclui. Quem salva ou finaliza uma base passa a ser o dono, e
 * ninguém grava na ficha de outro analista.
 */
class AnalysisRecordService
{
    /** Revisão inicial materializada pela pré-análise (10-08) ou defensivamente aqui. */
    private const REVISAO_INICIAL = 1;

    /** Campo da divergência por CNAE (status sugerido pelo motor × escolhido). */
    private const CAMPO_STATUS = 'status';

    public function __construct(
        private readonly AuditService $audit,
        private readonly PreAnaliseService $preAnalise,
        private readonly AnalysisStatusStateMachine $status,
    ) {}

    /** Estados em que salvar a ficha é começar (ou retomar) a análise: o status passa a "Em análise". */
    private const ESTADOS_QUE_INICIAM_ANALISE = [
        AnalysisStatus::Analisar,
        AnalysisStatus::Vistoriado,
        AnalysisStatus::ConviteRespondido,
        AnalysisStatus::ConviteCancelado,
    ];

    /**
     * Ficha que o usuário vê ao abrir (HU.183): a última ficha dele no processo;
     * sem ficha própria, a base disponível para ele; sem base, a última ficha do
     * processo, que é de outro analista e abre só para leitura. Abrir não cria
     * ficha: a dele nasce em novaRevisao. Sem usuário: a revisão de maior número
     * do processo, para rotinas internas.
     */
    public function current(ViabilityRequest $request, ?User $usuario = null): AnalysisRecord
    {
        $vigenteDoProcesso = $this->vigenteDoProcesso($request);

        if ($usuario === null) {
            return $vigenteDoProcesso;
        }

        return $this->ultimaDoAnalista($request, $usuario) ?? $this->baseDisponivel($request, $usuario) ?? $vigenteDoProcesso;
    }

    private function ultimaDoAnalista(ViabilityRequest $request, User $usuario): ?AnalysisRecord
    {
        return $request->analysisRecords()
            ->where('analyst_user_id', $usuario->id)
            ->where('status', '!=', AnalysisRecordStatus::Substituida->value)
            ->orderByDesc('revision')
            ->first();
    }

    /**
     * A ficha é de outro analista: quem a vê não grava nela.
     */
    public function eDeOutroAnalista(AnalysisRecord $record, ?User $usuario): bool
    {
        return $usuario !== null && $record->analyst_user_id !== null && $record->analyst_user_id !== $usuario->id;
    }

    /**
     * Finaliza a ficha usada numa conclusão. A ficha de outro analista é
     * finalizada no nome de quem a escreveu: a decisão fica com quem conclui e o
     * parecer com quem analisou.
     */
    public function finalizarParaConcluir(AnalysisRecord $record, User $ator): AnalysisRecord
    {
        return $this->finalizar($record, $this->eDeOutroAnalista($record, $ator) ? null : $ator);
    }

    /**
     * Revisão de maior número do processo. Defensivamente cria a revisão 1 vazia
     * se não houver nenhuma — normalmente a pré-análise (10-08) já a criou.
     */
    private function vigenteDoProcesso(ViabilityRequest $request): AnalysisRecord
    {
        // Depois da primeira revisão a vigente é sempre a mais recente: a pré-análise
        // só vale para criar ou atualizar a revisão inicial.
        $maisRecente = $request->analysisRecords()->orderByDesc('revision')->first();

        if ($maisRecente !== null && $maisRecente->revision > 1) {
            return $maisRecente;
        }

        $preAnalisada = $this->preAnalise->preAnalisar($request);

        if ($preAnalisada !== null) {
            return $preAnalisada;
        }

        $record = $request->analysisRecords()->orderByDesc('revision')->first();

        if ($record !== null) {
            return $record;
        }

        try {
            return $this->criarRevisaoInicialVazia($request);
        } catch (QueryException $e) {
            // Corrida: outra execução criou a revisão 1 concorrentemente (a unique
            // viability_request_id+revision barra a 2ª). Devolve a existente.
            return $request->analysisRecords()->orderByDesc('revision')->first() ?? throw $e;
        }
    }

    /**
     * Autosave do rascunho (RN-008): atualiza só os campos enviados (status
     * escolhido por CNAE, condicionantes, vagas e parecer). A revisão finalizada é
     * imutável (RN-003) — autosave nela lança AnalysisRecordImutavelException
     * (422 no caller). A sugestão do motor (status_sugerido) é preservada.
     * Quem salva uma base sem dono passa a ser o analista responsável por ela: é
     * isso que distingue a ficha salva da que só nasceu pré-analisada pelo motor.
     * Ficha de outro analista lança FichaDeOutroAnalistaException (HU.183).
     *
     * @param  array<string, mixed>  $data
     */
    public function autosave(AnalysisRecord $record, array $data, ?User $ator = null): AnalysisRecord
    {
        if ($record->status !== AnalysisRecordStatus::Rascunho) {
            throw AnalysisRecordImutavelException::finalizada($record);
        }

        $this->exigirDono($record, $ator);

        if (array_key_exists('per_cnae', $data) && is_array($data['per_cnae'])) {
            $record->per_cnae = $this->mergePerCnae($record->per_cnae, $data['per_cnae']);
        }

        if (array_key_exists('conditions', $data) && is_array($data['conditions'])) {
            $record->conditions = array_values($data['conditions']);
        }

        if (array_key_exists('parking', $data) && is_array($data['parking'])) {
            $record->parking = $data['parking'];
        }

        if (array_key_exists('parecer', $data)) {
            $record->parecer = $data['parecer'];
        }

        if (array_key_exists('is_virtual_office_hq', $data)) {
            $record->is_virtual_office_hq = $data['is_virtual_office_hq'];
        }

        if (array_key_exists('address_confirmed', $data)) {
            $record->address_confirmed = $data['address_confirmed'];
        }

        if ($ator !== null) {
            $record->analyst_user_id = $ator->id;
        }

        $record->save();

        if ($ator !== null) {
            $this->iniciarAnaliseSePreciso($record, $ator);
        }

        return $record;
    }

    /**
     * Quem salva a ficha começa a análise: o processo que estava só distribuído
     * (ou voltou da vistoria ou do convite) passa a "Em análise" sem ação manual.
     */
    private function iniciarAnaliseSePreciso(AnalysisRecord $record, User $ator): void
    {
        $request = $record->viabilityRequest()->first();

        if ($request === null || ! in_array($request->analysis_status, self::ESTADOS_QUE_INICIAM_ANALISE, true)) {
            return;
        }

        if (! $this->status->canTransition($request->analysis_status, AnalysisStatus::EmAnalise)) {
            return;
        }

        $this->status->transition($request, AnalysisStatus::EmAnalise, $ator, 'Analista iniciou o preenchimento da ficha de análise.');
    }

    /**
     * Finaliza a revisão (HU-126 RN-003): torna-a IMUTÁVEL (status finalizada +
     * finalized_at) e materializa as divergências analista×motor (HU-130 RN-002)
     * numa transação com a auditoria síncrona (RN-002). Finalizar uma revisão já
     * finalizada lança AnalysisRecordImutavelException (422 no caller). NÃO decide
     * o processo — a decisão é 10-10, a partir da ficha finalizada.
     */
    public function finalizar(AnalysisRecord $record, ?User $ator = null): AnalysisRecord
    {
        if ($record->status !== AnalysisRecordStatus::Rascunho) {
            throw AnalysisRecordImutavelException::finalizada($record);
        }

        $this->exigirDono($record, $ator);

        return DB::transaction(function () use ($record, $ator): AnalysisRecord {
            $divergencias = $this->registrarDivergencias($record);

            $record->status = AnalysisRecordStatus::Finalizada;
            $record->finalized_at = now();

            if ($ator !== null) {
                $record->analyst_user_id = $ator->id;
            }

            $record->save();

            $this->audit->log(
                logName: 'analise',
                event: 'ficha-finalizar',
                description: "Finalização da ficha de análise do processo #{$record->viability_request_id} (revisão {$record->revision})",
                properties: [
                    'viability_request_id' => $record->viability_request_id,
                    'revision' => $record->revision,
                    'divergencias' => $divergencias,
                ],
                subject: $record,
                result: 'sucesso',
                rulesVersion: $this->rulesVersionRepresentativa($record->engine_rules_versions),
            );

            return $record;
        });
    }

    /**
     * Ao ir para conclusão, a ficha de quem encaminhou é finalizada. Com a chave
     * `viabiliza.analise.ficha.conclusao_parte_da_ficha_do_analista` ligada,
     * quem conclui recebe uma cópia sem dono dessa ficha; desligada, recebe a
     * base do motor ao abrir. Devolve a cópia ou, sem ela, a ficha finalizada.
     */
    public function guardarParaConclusao(ViabilityRequest $request, User $ator): AnalysisRecord
    {
        $record = $this->current($request, $ator);

        if ($record->status === AnalysisRecordStatus::Rascunho) {
            $record = $this->finalizar($record, $ator);
        }

        if (! config('viabiliza.analise.ficha.conclusao_parte_da_ficha_do_analista', true)) {
            return $record;
        }

        return $this->copiar($request, $record, dono: null, evento: 'ficha-copia-conclusao');
    }

    /**
     * Abre a ficha do analista (HU.183): copia a última ficha DELE, que fica
     * intacta (append-only). Com rascunho aberto, devolve o rascunho em vez de
     * abrir outro. Sem ficha própria, devolve a base disponível ou cria a base do
     * motor. Sem usuário (rotinas internas), copia a última revisão do processo.
     */
    public function novaRevisao(ViabilityRequest $request, ?User $ator = null): AnalysisRecord
    {
        if ($ator === null) {
            $ultima = $request->analysisRecords()->orderByDesc('revision')->first();

            return $ultima === null
                ? $this->current($request)
                : $this->copiar($request, $ultima, dono: $ultima->analyst_user_id, evento: 'ficha-nova-revisao');
        }

        $propria = $this->ultimaDoAnalista($request, $ator);

        if ($propria === null) {
            return $this->baseDisponivel($request, $ator) ?? $this->criarBaseDoMotor($request);
        }

        if ($propria->status === AnalysisRecordStatus::Rascunho) {
            return $propria;
        }

        return $this->copiar($request, $propria, dono: $ator->id, evento: 'ficha-nova-revisao');
    }

    /**
     * Cria a próxima revisão (rascunho) copiando $origem, que permanece intacta.
     */
    private function copiar(ViabilityRequest $request, AnalysisRecord $origem, ?int $dono, string $evento): AnalysisRecord
    {
        return DB::transaction(function () use ($request, $origem, $dono, $evento): AnalysisRecord {
            $nova = AnalysisRecord::create([
                'viability_request_id' => $request->id,
                'revision' => $this->proximaRevisao($request),
                'status' => AnalysisRecordStatus::Rascunho,
                'analyst_user_id' => $dono,
                'based_on_record_id' => $origem->id,
                'engine_available' => $origem->engine_available,
                'engine_snapshot' => $origem->engine_snapshot,
                'engine_rules_versions' => $origem->engine_rules_versions,
                'per_cnae' => $origem->per_cnae,
                'conditions' => $origem->conditions,
                'analysis_reasons' => $origem->motivosSistema() ?: null,
                'parking' => $origem->parking,
                'address_confirmed' => $origem->address_confirmed,
                'parecer' => $origem->parecer,
                'is_virtual_office_hq' => $origem->is_virtual_office_hq,
                'finalized_at' => null,
            ]);

            $this->audit->log(
                logName: 'analise',
                event: $evento,
                description: "Nova revisão da ficha de análise do processo #{$request->id} (revisão {$nova->revision}, a partir da revisão {$origem->revision})",
                properties: [
                    'viability_request_id' => $request->id,
                    'revision_anterior' => $origem->revision,
                    'revision' => $nova->revision,
                ],
                subject: $nova,
                result: 'sucesso',
            );

            return $nova;
        });
    }

    /**
     * Base sem dono que serve a $usuario: a cópia de conclusão só para quem
     * conclui a análise; a base do motor para qualquer analista.
     */
    private function baseDisponivel(ViabilityRequest $request, User $usuario): ?AnalysisRecord
    {
        $podeConcluir = $usuario->can('concluir-analise');

        return $request->analysisRecords()
            ->whereNull('analyst_user_id')
            ->where('status', AnalysisRecordStatus::Rascunho->value)
            ->orderByDesc('revision')
            ->get()
            ->first(fn (AnalysisRecord $base): bool => $base->based_on_record_id === null || $podeConcluir);
    }

    /**
     * Base do motor para quem ainda não tem ficha no processo: a última revisão
     * sem o que é opinião do analista (escolha e justificativa por atividade,
     * parecer, confirmação de endereço e sede de escritório virtual). Ficam o
     * snapshot do motor, o enquadramento das atividades, as condicionantes e as
     * vagas, que descrevem o imóvel e a atividade.
     */
    private function criarBaseDoMotor(ViabilityRequest $request): AnalysisRecord
    {
        $ultima = $this->vigenteDoProcesso($request);

        $perCnae = array_map(function (mixed $item): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $item['status_escolhido'] = $item['status_sugerido'] ?? null;
            $item['justificativa'] = null;

            return $item;
        }, $ultima->per_cnae ?? []);

        try {
            return AnalysisRecord::create([
                'viability_request_id' => $request->id,
                'revision' => $this->proximaRevisao($request),
                'status' => AnalysisRecordStatus::Rascunho,
                'analyst_user_id' => null,
                'engine_available' => $ultima->engine_available,
                'engine_snapshot' => $ultima->engine_snapshot,
                'engine_rules_versions' => $ultima->engine_rules_versions,
                'per_cnae' => $ultima->per_cnae === null ? null : $perCnae,
                'conditions' => $ultima->conditions,
                'analysis_reasons' => $ultima->motivosSistema() ?: null,
                'parking' => $ultima->parking,
                'parecer' => null,
                'finalized_at' => null,
            ]);
        } catch (QueryException $e) {
            // Corrida: outra abertura criou a base ao mesmo tempo (unique por revisão).
            return $request->analysisRecords()
                ->whereNull('analyst_user_id')
                ->whereNull('based_on_record_id')
                ->where('status', AnalysisRecordStatus::Rascunho->value)
                ->orderByDesc('revision')
                ->first() ?? throw $e;
        }
    }

    private function proximaRevisao(ViabilityRequest $request): int
    {
        return ((int) $request->analysisRecords()->max('revision')) + 1;
    }

    /**
     * Ninguém grava na ficha de outro analista (HU.183). Base sem dono passa a
     * ser de quem a salva ou finaliza.
     */
    private function exigirDono(AnalysisRecord $record, ?User $ator): void
    {
        if ($ator === null || $record->analyst_user_id === null || $record->analyst_user_id === $ator->id) {
            return;
        }

        throw FichaDeOutroAnalistaException::de($record);
    }

    /**
     * Materializa as divergências (HU-130 RN-002): para cada CNAE onde o status
     * ESCOLHIDO pelo analista diverge do SUGERIDO pelo motor, grava uma
     * analysis_divergences (com a justificativa, quando informada). Concordâncias e
     * itens sem sugestão do motor (FA-01) NÃO geram divergência — anti-fachada.
     *
     * @return int Quantidade de divergências gravadas (para a auditoria).
     */
    private function registrarDivergencias(AnalysisRecord $record): int
    {
        $total = 0;

        foreach ($record->per_cnae ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $sugerido = $item['status_sugerido'] ?? null;
            $escolhido = $item['status_escolhido'] ?? null;

            if ($sugerido === null || $escolhido === null || (string) $sugerido === (string) $escolhido) {
                continue;
            }

            AnalysisDivergence::create([
                'analysis_record_id' => $record->id,
                'cnae' => (string) ($item['cnae'] ?? ''),
                'field' => self::CAMPO_STATUS,
                'suggested_value' => (string) $sugerido,
                'final_value' => (string) $escolhido,
                'justification' => $item['justificativa'] ?? null,
            ]);

            $total++;
        }

        return $total;
    }

    /**
     * Versão de regra representativa para a coluna rules_version da auditoria: a
     * primeira versão real do engine_rules_versions da ficha. O mapa completo já
     * vive em engine_rules_versions (a explicabilidade detalhada é de 10-10/12).
     *
     * @param  array<string, mixed>|null  $rulesVersions
     */
    private function rulesVersionRepresentativa(?array $rulesVersions): ?string
    {
        foreach ($rulesVersions ?? [] as $grupo) {
            if (is_array($grupo)) {
                foreach ($grupo as $versao) {
                    if (is_string($versao) && $versao !== '') {
                        return $versao;
                    }
                }

                continue;
            }

            if (is_string($grupo) && $grupo !== '') {
                return $grupo;
            }
        }

        return null;
    }

    /**
     * Funde a escolha do analista (status_escolhido/justificativa/condicionantes
     * e o enquadramento validado — codigo_louos/codigo_tll, usabilidade SEDUR
     * item 23) na lista vigente, casando por `cnae` e PRESERVANDO os campos do
     * motor (status_sugerido, tendência, fundamentação). CNAEs ausentes na ficha
     * (modo manual) entram como novos itens.
     *
     * @param  array<int, mixed>|null  $existing
     * @param  array<int, mixed>  $incoming
     * @return list<array<string, mixed>>
     */
    private function mergePerCnae(?array $existing, array $incoming): array
    {
        $base = [];
        foreach ($existing ?? [] as $item) {
            if (is_array($item) && isset($item['cnae'])) {
                $base[(string) $item['cnae']] = $item;
            }
        }

        foreach ($incoming as $item) {
            if (! is_array($item) || ! isset($item['cnae'])) {
                continue;
            }

            $cnae = (string) $item['cnae'];
            $atual = $base[$cnae] ?? ['cnae' => $cnae];

            foreach (['status_escolhido', 'justificativa', 'condicionantes', 'codigo_louos', 'codigo_tll'] as $campo) {
                if (array_key_exists($campo, $item)) {
                    $atual[$campo] = $item[$campo];
                }
            }

            $base[$cnae] = $atual;
        }

        return array_values($base);
    }

    private function criarRevisaoInicialVazia(ViabilityRequest $request): AnalysisRecord
    {
        return AnalysisRecord::create([
            'viability_request_id' => $request->id,
            'revision' => self::REVISAO_INICIAL,
            'status' => AnalysisRecordStatus::Rascunho,
            'analyst_user_id' => null,
            'engine_available' => false,
            'engine_snapshot' => null,
            'engine_rules_versions' => null,
            'per_cnae' => null,
            'conditions' => [],
            'parking' => [],
            'parecer' => null,
            'finalized_at' => null,
        ]);
    }
}
