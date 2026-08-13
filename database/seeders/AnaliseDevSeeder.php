<?php

namespace Database\Seeders;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\DocumentRequirement;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use App\Services\Analise\AnalysisRecordService;
use App\Services\Analise\DistribuicaoService;
use App\Services\Analise\MalhaFinaService;
use App\Services\Analise\PendenciaService;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Solicitacao\DocumentRequirementResolver;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Support\DemoMode;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Processos de EXEMPLO da análise técnica (EP10) em cada estágio, para o cidadão
 * de desenvolvimento (cidadao@sile.dev) — tornando a fila, a caixa, a ficha e a
 * decisão humana NAVEGÁVEIS de ponta a ponta. Dados FICTÍCIOS, lógica REAL
 * (entrega-funcional): cada estágio é alcançado pelos SERVIÇOS REAIS —
 * FluxoExpressoService (encaminha à análise), DistribuicaoService (distribui/
 * assume), AnalysisRecordService (ficha/finalização), AnaliseTecnicaDecisionService
 * (defere + TVL — ViabilityDecision flow 'analise_tecnica'), PendenciaService e
 * MalhaFinaService. Nada é simulado.
 *
 *  1. EM ANÁLISE (distribuído, ficha pronta para decidir) — alvo do comando de
 *     evidência `analise:decidir <id> --finalizar`.
 *  2. DEFERIDO PELO ANALISTA (ficha finalizada → deferida + TVL) e, sobre ele,
 *     um encaminhamento à MALHA FINA (corrige o bug legado: atinge deferido).
 *  3. EM PENDÊNCIA (pendência aberta — ciclo HU-083/084 interno).
 *
 * ROTEAMENTO AO SETOR — pendência honesta: o FluxoExpressoService deixa o
 * sector_id NULO ao encaminhar (a regra de roteamento automático ao setor é
 * pendência SEDUR — em produção o gestor atribui a caixa manualmente). Aqui no
 * DEV o seed coloca os processos no setor de triagem (SectorSeeder) para
 * destravar a navegação; NÃO inventa uma regra de roteamento.
 *
 * Gate de ambiente: NUNCA em produção (igual ao ExpressoDevSeeder). Driver-aware:
 * a transição protocolada→em_analise reexecuta os motores territoriais (PostGIS),
 * então os estágios só são montados em pgsql; em SQLite o seed é no-op (a prova
 * com a cadeia real é dos testes @group postgis — AnaliseSeedPostgisTest),
 * consistente com a Fase 9. Idempotente: marcador estável em address_reference +
 * guardas por estágio. Depende de SectorSeeder, ExpressoDevSeeder e catálogos.
 */
class AnaliseDevSeeder extends Seeder
{
    public const MARK_EM_ANALISE = 'Análise dev — em análise (distribuído, pronto p/ decidir)';

    public const MARK_DEFERIDO = 'Análise dev — deferido pelo analista (com malha fina)';

    public const MARK_PENDENCIA = 'Análise dev — em pendência';

    public function run(): void
    {
        // GATE DE AMBIENTE (anti-fachada): exemplos da análise só em dev/teste.
        if (! DemoMode::allowsDemoSeeders()) {
            $this->command?->warn('AnaliseDevSeeder: ignorado fora de dev/teste.');

            return;
        }

        // Driver-aware: a cadeia real até em_analise (FluxoExpressoService::decide)
        // reexecuta os motores territoriais — exige PostGIS. Em SQLite os estágios
        // não são montados (degradação honesta); a prova é em @group postgis.
        if (DB::getDriverName() !== 'pgsql') {
            $this->command?->warn('AnaliseDevSeeder: estágios da análise exigem PostGIS — pulado em SQLite (degradação honesta).');

            return;
        }

        $contexto = $this->contexto();

        if ($contexto === null) {
            $this->command?->warn('AnaliseDevSeeder: pré-requisitos ausentes (cidadão/empresa/tipo/CNAE/fachada/setor/analista/gestor) — exemplos não criados.');

            return;
        }

        $this->exemploEmAnalise($contexto);
        $this->exemploDeferidoComMalhaFina($contexto);
        $this->exemploEmPendencia($contexto);
    }

    /**
     * Estágio 1 — EM ANÁLISE: encaminhado, na caixa do setor, distribuído pelo
     * gestor ao analista, com a ficha preenchida (status escolhido) mas em
     * RASCUNHO: pronta para o `analise:decidir <id> --finalizar` finalizar e
     * deferir, gerando evidência real.
     *
     * @param  AnaliseDevContexto  $ctx
     */
    private function exemploEmAnalise(array $ctx): void
    {
        $request = $this->encaminhar($ctx, self::MARK_EM_ANALISE);

        if ($request === null || $request->status !== ViabilityRequestStatus::EmAnalise) {
            return;
        }

        $this->colocarNaCaixa($request, $ctx['sector']);

        if ($request->assigned_user_id === null) {
            app(DistribuicaoService::class)->distribuir($request->fresh(), $ctx['analista'], $ctx['gestor']);
        }

        $this->preencherFichaParaDeferir($request->fresh(), $ctx['analista']);
    }

    /**
     * Estágio 2 — DEFERIDO PELO ANALISTA + MALHA FINA: a ficha é finalizada e a
     * decisão humana DEFERE de verdade (ViabilityDecision flow 'analise_tecnica'
     * + TVL); em seguida o processo é encaminhado à malha fina (revisão sobre um
     * deferido — RN-001 da HU-136).
     *
     * @param  AnaliseDevContexto  $ctx
     */
    private function exemploDeferidoComMalhaFina(array $ctx): void
    {
        $request = $this->encaminhar($ctx, self::MARK_DEFERIDO);

        if ($request === null) {
            return;
        }

        if ($request->status === ViabilityRequestStatus::EmAnalise) {
            $this->colocarNaCaixa($request, $ctx['sector']);

            if ($request->assigned_user_id === null) {
                app(DistribuicaoService::class)->assumir($request->fresh(), $ctx['analista']);
            }

            $record = $this->preencherFichaParaDeferir($request->fresh(), $ctx['analista']);

            if (! $record->isFinalizada()) {
                $record = app(AnalysisRecordService::class)->finalizar($record, $ctx['analista']);
            }

            app(AnaliseTecnicaDecisionService::class)->decide($record, $ctx['analista']);
        }

        // Malha fina sobre o deferido (idempotente: só se ainda não está nela).
        $request = $request->fresh();

        if ($request->status === ViabilityRequestStatus::Deferida && ! $request->in_fine_mesh) {
            app(MalhaFinaService::class)->encaminhar(
                $request,
                $ctx['gestor'],
                'Revisão de amostragem (malha fina) — exemplo de desenvolvimento.',
            );
        }
    }

    /**
     * Estágio 3 — EM PENDÊNCIA: o analista abre uma pendência ao requerente,
     * movendo o processo para em_pendencia (ciclo HU-083/084 interno).
     *
     * @param  AnaliseDevContexto  $ctx
     */
    private function exemploEmPendencia(array $ctx): void
    {
        $request = $this->encaminhar($ctx, self::MARK_PENDENCIA);

        if ($request === null || $request->status !== ViabilityRequestStatus::EmAnalise) {
            return;
        }

        $this->colocarNaCaixa($request, $ctx['sector']);

        if ($request->assigned_user_id === null) {
            app(DistribuicaoService::class)->assumir($request->fresh(), $ctx['analista']);
        }

        app(PendenciaService::class)->abrir(
            $request->fresh(),
            $ctx['analista'],
            'Apresentar planta de situação atualizada e comprovante de uso do imóvel.',
        );
    }

    /**
     * Cria (ou reusa) o rascunho de exemplo e o leva até em_analise pela cadeia
     * REAL: protocola (ProtocolarSolicitacaoService) e, em pgsql, decide
     * (FluxoExpressoService::decide) — sem zona (Pituba), o motor degrada
     * honestamente para em_analise, disparando a pré-análise (ficha rev 1).
     * Idempotente: já em_analise (re-seed) não reprotocola nem redecide.
     *
     * @param  AnaliseDevContexto  $ctx
     */
    private function encaminhar(array $ctx, string $marker): ?ViabilityRequest
    {
        $request = $this->exampleDraft($ctx, $marker);

        if ($request->status === ViabilityRequestStatus::Rascunho) {
            app(ProtocolarSolicitacaoService::class)->protocol($request, $ctx['cidadao']);
        }

        if ($request->fresh()->status === ViabilityRequestStatus::Protocolada) {
            app(FluxoExpressoService::class)->decide($request->fresh());
        }

        return $request->fresh();
    }

    /**
     * Coloca o processo na caixa do setor de triagem (sector_id) — o passo que em
     * produção é a atribuição manual do gestor (roteamento automático pendente
     * SEDUR). forceFill: sector_id fica fora do fillable (escrita controlada).
     */
    private function colocarNaCaixa(ViabilityRequest $request, Sector $sector): void
    {
        if ($request->sector_id === null) {
            $request->forceFill(['sector_id' => $sector->id])->save();
        }
    }

    /**
     * Preenche a ficha vigente com o status ESCOLHIDO 'deferida' por CNAE e um
     * parecer, pelo autosave REAL (AnalysisRecordService). É a decisão do humano
     * sobre o caso pendente (sem zona oficial), com fundamentação própria —
     * deixando a ficha pronta para finalizar/deferir.
     */
    private function preencherFichaParaDeferir(ViabilityRequest $request, User $analista): AnalysisRecord
    {
        $service = app(AnalysisRecordService::class);
        $record = $service->current($request);

        if ($record->isFinalizada()) {
            return $record;
        }

        $perCnae = [];

        foreach ($request->cnaes as $cnae) {
            $perCnae[] = [
                'cnae' => $cnae->code,
                'status_escolhido' => DecisionOutcome::Deferida->value,
                'justificativa' => 'Uso compatível com a vizinhança; deferido pela análise técnica (caso sem zona oficial).',
            ];
        }

        return $service->autosave($record, [
            'per_cnae' => $perCnae,
            'parecer' => 'Parecer técnico favorável: atividade compatível com o local, com fundamentação na LOUOS (Lei nº 9.148/2016).',
        ]);
    }

    /**
     * Cria (ou reusa) um rascunho instruído de exemplo num imóvel FORA da zona
     * fictícia (Pituba) — sem zona oficial, o motor encaminha à análise. FKs
     * explícitos preservam as contagens do seed. Idempotente pelo marcador.
     *
     * @param  AnaliseDevContexto  $ctx
     */
    private function exampleDraft(array $ctx, string $marker): ViabilityRequest
    {
        $existing = ViabilityRequest::query()
            ->where('requester_user_id', $ctx['cidadao']->id)
            ->where('address_reference', $marker)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $request = ViabilityRequest::factory()->draft()->create([
            'origin' => ViabilityRequestOrigin::Portal,
            'service_type_id' => $ctx['type']->id,
            'company_id' => $ctx['company']->id,
            'requester_user_id' => $ctx['cidadao']->id,
            'created_by_user_id' => $ctx['cidadao']->id,
            'used_area_m2' => 120.0,
            'address_street' => 'Rua Ceará',
            'address_number' => '300',
            'address_neighborhood' => 'Pituba',
            'address_zip' => '41830000',
            'address_reference' => $marker,
            'property_polygon_geojson' => self::poligonoPituba(),
            'is_virtual_office' => false,
            'is_public_area' => false,
            'has_independent_access' => true,
        ]);

        $request->cnaes()->attach($ctx['cnae']->id, ['is_primary' => true]);

        $request->documents()->create([
            'requirement_id' => $ctx['fachada']->id,
            'disk' => (string) Settings::get('storage.documentos.disk', config('sile.storage.documentos.disk', 'local')),
            'path' => "solicitacoes/seed/{$request->id}-fachada.jpg",
            'original_name' => 'fachada-exemplo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 51200,
            'sha256' => hash('sha256', "analise-fachada-{$request->id}"),
            'uploaded_by_user_id' => $ctx['cidadao']->id,
        ]);

        return $request;
    }

    /**
     * Pré-requisitos do seed (cidadão/empresa/tipo/CNAE/fachada + setor/analista/
     * gestor do SectorSeeder). Devolve null se faltar algo (degradação honesta).
     *
     * @phpstan-type AnaliseDevContexto array{cidadao: User, company: Company, type: ViabilityServiceType, cnae: Cnae, fachada: DocumentRequirement, sector: Sector, analista: User, gestor: User}
     *
     * @return AnaliseDevContexto|null
     */
    private function contexto(): ?array
    {
        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->first();

        $company = $cidadao === null ? null : Company::query()
            ->whereHas('links', fn ($query) => $query->where('user_id', $cidadao->id)->whereNull('ended_at'))
            ->orderBy('id')
            ->first();

        $type = ViabilityServiceType::query()->where('code', 'viabilidade-1-estabelecimento')->first()
            ?? ViabilityServiceType::query()->where('active', true)->orderBy('id')->first();

        $cnae = Cnae::query()->where('code', '4712100')->first()
            ?? Cnae::query()->where('active', true)->orderBy('code')->first();

        $fachada = DocumentRequirement::query()->where('code', DocumentRequirementResolver::CODE_FACHADA)->first();

        $sector = Sector::query()->where('name', SectorSeeder::SECTOR_NAME)->first();
        $analista = User::query()->where('email', 'analista@sile.dev')->first();
        $gestor = User::query()->where('email', 'gestor@sile.dev')->first();

        if ($cidadao === null || $company === null || $type === null || $cnae === null
            || $fachada === null || $sector === null || $analista === null || $gestor === null) {
            return null;
        }

        return compact('cidadao', 'company', 'type', 'cnae', 'fachada', 'sector', 'analista', 'gestor');
    }

    /**
     * Polígono na Pituba — FORA da zona fictícia (ZonaFicticiaDevSeeder cobre o
     * Centro): sem zona, o motor degrada honestamente para em_analise.
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    private static function poligonoPituba(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.4585, -12.9950],
                [-38.4585, -12.9940],
                [-38.4575, -12.9940],
                [-38.4575, -12.9950],
                [-38.4585, -12.9950],
            ]],
        ];
    }
}
