<?php

namespace Database\Seeders;

use App\Enums\CompanyLinkRole;
use App\Enums\CompanySource;
use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\DocumentRequirement;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Abuso\AbuseDetectionService;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use App\Services\Analise\AnalysisRecordService;
use App\Services\Solicitacao\DocumentRequirementResolver;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use App\Support\DemoMode;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Fechamento do EP12: torna a AUDITORIA, a EXPLICABILIDADE, a LGPD e a DETECÇÃO
 * DE ABUSO navegáveis no desenvolvimento com dados REAIS — fictícios na carga,
 * mas produzidos pela LÓGICA de verdade (entrega-funcional), nunca linhas
 * fabricadas. Tudo por requerentes/empresas DEDICADOS (jamais o cidadao@sile.dev),
 * para não perturbar as contagens já asseridas dos exemplos das fases anteriores.
 *
 *  1. PADRÃO DE ABUSO REAL (HU-149): semeia um volume atípico de solicitações do
 *     MESMO CNPJ (empresa dedicada), habilita features.deteccao_abuso SÓ durante
 *     o seed e roda o AbuseDetectionService REAL — que gera os abuse_alerts de
 *     verdade e, acima do limiar (severidade Alta), encaminha 1 processo à malha
 *     fina pelo CAMINHO DE SISTEMA. Ao final RESTAURA o toggle para OFF (a feature
 *     nasce desligada — degradação honesta; a SEDUR liga após calibrar os limiares).
 *  2. ACESSO A DADO PESSOAL REAL (HU-102): registra, pelo MESMO AuditService do
 *     ProcessoController@show (personal_data=true, gestor como causer), um acesso
 *     ao detalhe de um processo de terceiro — base de medição do painel LGPD.
 *  3. EXPLICABILIDADE NAVEGÁVEL (HU-099): leva um processo dedicado à decisão pela
 *     ANÁLISE TÉCNICA a partir da ficha (territorial-agnóstica, roda em SQLite) —
 *     a ViabilityDecision nasce com decision_trace passo a passo; e semeia uma
 *     decisão LEGADA via factory SEM decision_trace (sub-passo "não registrado").
 *
 * Gate de ambiente: NUNCA em produção (igual aos demais seeders dev). Roda em
 * SQLite E pgsql (a detecção e a decisão da análise técnica não exigem PostGIS).
 * Idempotente: marcadores estáveis próprios + dedup do ledger de abuso. Roda por
 * último no DatabaseSeeder; depende de SectorSeeder (analista/gestor), catálogos
 * (tipo/CNAE/requisito de fachada) e do ParameterSeeder (toggle + limiares).
 */
class AuditoriaDevSeeder extends Seeder
{
    /** CNPJ dedicado do padrão de abuso (volume atípico do mesmo CNPJ). */
    public const CNPJ_ABUSO = '99999999000199';

    /** CNPJ dedicado dos exemplos de decisão/explicabilidade. */
    public const CNPJ_AUDITORIA = '88888888000188';

    public const EMAIL_REQUERENTE_ABUSO = 'requerente.abuso@sile.dev';

    public const EMAIL_REQUERENTE_AUDITORIA = 'requerente.auditoria@sile.dev';

    public const MARK_ABUSO = 'Auditoria dev — padrão de abuso (volume CNPJ)';

    public const MARK_DECISAO = 'Auditoria dev — decisão navegável (análise técnica)';

    public const MARK_LEGADO = 'Auditoria dev — decisão legada (sem trace)';

    /**
     * Volume do padrão dedicado — acima de 2× o limite default de volume_cnpj
     * (abuso.volume_cnpj.limite = 5 → Alta quando > 10), garantindo severidade
     * ALTA e, com ela, o encaminhamento à malha fina.
     */
    public const VOLUME_ABUSO = 12;

    public function run(): void
    {
        // GATE DE AMBIENTE (anti-fachada): exemplos de dev só em dev/teste. Em
        // produção a detecção roda pelo scheduler quando a SEDUR ligar o toggle.
        if (! DemoMode::allowsDemoSeeders()) {
            $this->command?->warn('AuditoriaDevSeeder: ignorado fora de dev/teste.');

            return;
        }

        $ctx = $this->contexto();

        if ($ctx === null) {
            $this->command?->warn('AuditoriaDevSeeder: pré-requisitos ausentes (tipo/CNAE/fachada/analista/gestor) — exemplos não criados.');

            return;
        }

        $this->seedDecisaoNavegavel($ctx);
        $this->seedPadraoAbuso($ctx);
        $this->detectarAbusoReal();
        $this->registrarAcessoDadoPessoal($ctx);
    }

    /**
     * Pré-requisitos (catálogos + usuários de gestão do SectorSeeder). Devolve
     * null se faltar algo (degradação honesta — nunca cria exemplo pela metade).
     *
     * @phpstan-type AuditoriaDevContexto array{type: ViabilityServiceType, cnae: Cnae, fachada: DocumentRequirement, analista: User, gestor: User}
     *
     * @return AuditoriaDevContexto|null
     */
    private function contexto(): ?array
    {
        $type = ViabilityServiceType::query()->where('code', 'viabilidade-1-estabelecimento')->first()
            ?? ViabilityServiceType::query()->where('active', true)->orderBy('id')->first();

        $cnae = Cnae::query()->where('code', '4712100')->first()
            ?? Cnae::query()->where('active', true)->orderBy('code')->first();

        $fachada = DocumentRequirement::query()->where('code', DocumentRequirementResolver::CODE_FACHADA)->first();

        $analista = User::query()->where('email', 'analista@sile.dev')->first();
        $gestor = User::query()->where('email', 'gestor@sile.dev')->first();

        if ($type === null || $cnae === null || $fachada === null || $analista === null || $gestor === null) {
            return null;
        }

        return compact('type', 'cnae', 'fachada', 'analista', 'gestor');
    }

    /**
     * Explicabilidade navegável (HU-099): um processo dedicado é DECIDIDO pela
     * análise técnica (decision_trace real) e uma decisão LEGADA nasce sem trace.
     *
     * @param  AuditoriaDevContexto  $ctx
     */
    private function seedDecisaoNavegavel(array $ctx): void
    {
        $requerente = $this->requerente(self::EMAIL_REQUERENTE_AUDITORIA, 'Requerente Auditoria (dev)', '22233344455');
        $empresa = $this->empresa(self::CNPJ_AUDITORIA, 'EMPRESA AUDITORIA DEV LTDA', 'AUDITORIA DEV');
        $this->vincular($empresa, $requerente);

        $this->decisaoAnaliseTecnica($ctx, $requerente, $empresa);
        $this->decisaoLegada($ctx, $requerente, $empresa);
    }

    /**
     * Leva um processo dedicado à decisão pela ANÁLISE TÉCNICA (a partir da
     * ficha), pelo caminho REAL: protocola → em_analise (máquina de estados, o
     * mesmo mecanismo do ComunicacaoDevSeeder, territorial-agnóstico) → preenche e
     * finaliza a ficha → AnaliseTecnicaDecisionService::decide. A decisão nasce
     * com decision_trace (no caso sem motor, com os passos do motor marcados "não
     * registrado" — honesto). Idempotente: já decidido não redecide.
     *
     * @param  AuditoriaDevContexto  $ctx
     */
    private function decisaoAnaliseTecnica(array $ctx, User $requerente, Company $empresa): void
    {
        $request = $this->rascunhoInstruido(
            $ctx, $requerente, $empresa, self::MARK_DECISAO,
            'Rua da Explicabilidade', '500', 'Itapuã', '41610000', self::poligonoDecisao(),
        );

        if ($request->decision()->exists()) {
            return;
        }

        $request = $this->levarParaEmAnalise($request, $requerente, $ctx['analista']);

        if ($request === null) {
            $this->command?->warn('AuditoriaDevSeeder: processo dedicado não chegou a em_analise — decisão não concluída (degradação honesta).');

            return;
        }

        $service = app(AnalysisRecordService::class);
        $record = $service->current($request);

        if (! $record->isFinalizada()) {
            $record = $service->autosave($record, [
                'per_cnae' => [[
                    'cnae' => $ctx['cnae']->code,
                    'status_escolhido' => DecisionOutcome::Deferida->value,
                    'justificativa' => 'Uso compatível com a vizinhança; deferido pela análise técnica (exemplo de explicabilidade do EP12).',
                ]],
                'parecer' => 'Parecer técnico favorável, com fundamentação na LOUOS (Lei nº 9.148/2016) — exemplo navegável da explicabilidade.',
            ]);
            $record = $service->finalizar($record, $ctx['analista']);
        }

        app(AnaliseTecnicaDecisionService::class)->decide($record, $ctx['analista']);
    }

    /**
     * Decisão LEGADA (HU-099 — sub-passo "não registrado"): uma decisão antiga
     * gravada SEM decision_trace (a coluna é aditiva), para exercitar a marca
     * "não registrado nesta decisão" na explicabilidade. Idempotente pelo marcador.
     *
     * @param  AuditoriaDevContexto  $ctx
     */
    private function decisaoLegada(array $ctx, User $requerente, Company $empresa): void
    {
        if (ViabilityRequest::query()->where('address_reference', self::MARK_LEGADO)->exists()) {
            return;
        }

        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Indeferida,
            'origin' => ViabilityRequestOrigin::Portal,
            'service_type_id' => $ctx['type']->id,
            'company_id' => $empresa->id,
            'requester_user_id' => $requerente->id,
            'created_by_user_id' => $requerente->id,
            'protocol_number' => 'VIA-2025-099999',
            'protocoled_at' => now()->subMonths(8),
            'used_area_m2' => 90.0,
            'property_registration' => null,
            'address_street' => 'Rua do Legado',
            'address_number' => '12',
            'address_neighborhood' => 'Brotas',
            'address_zip' => '40285000',
            'address_reference' => self::MARK_LEGADO,
            'property_polygon_geojson' => self::poligonoLegado(),
            'is_virtual_office' => false,
            'is_public_area' => false,
            'has_independent_access' => true,
        ]);

        $request->cnaes()->attach($ctx['cnae']->id, ['is_primary' => true]);

        // Decisão sem decision_trace (null): o factory não preenche a coluna
        // aditiva — é exatamente a decisão legada que a HU-099 marca como
        // "não registrado nesta decisão".
        ViabilityDecision::factory()->indeferida()->create([
            'viability_request_id' => $request->id,
            'decided_at' => now()->subMonths(8),
        ]);
    }

    /**
     * Padrão de abuso DEDICADO (HU-149): VOLUME_ABUSO solicitações do MESMO CNPJ
     * (empresa dedicada) por um requerente próprio — volume atípico que o
     * VolumeCnpjDetector flagra em severidade Alta. Mesmo endereço/polígono em
     * todas (1 endereço distinto) para NÃO disparar o detector de polígono; sem
     * inscrição (property_registration null) para não disparar o de inscrição.
     * Idempotente: completa só até VOLUME_ABUSO drafts.
     *
     * @param  AuditoriaDevContexto  $ctx
     */
    private function seedPadraoAbuso(array $ctx): void
    {
        $requerente = $this->requerente(self::EMAIL_REQUERENTE_ABUSO, 'Requerente Abuso (dev)', '12345678909');
        $empresa = $this->empresa(self::CNPJ_ABUSO, 'EMPRESA ABUSO DEV LTDA', 'ABUSO DEV');
        $this->vincular($empresa, $requerente);

        $existentes = ViabilityRequest::query()->where('address_reference', self::MARK_ABUSO)->count();

        for ($i = $existentes; $i < self::VOLUME_ABUSO; $i++) {
            ViabilityRequest::factory()->draft()->create([
                'origin' => ViabilityRequestOrigin::Portal,
                'service_type_id' => $ctx['type']->id,
                'company_id' => $empresa->id,
                'requester_user_id' => $requerente->id,
                'created_by_user_id' => $requerente->id,
                'used_area_m2' => 80.0,
                'property_registration' => null,
                'address_street' => 'Rua do Volume Atípico',
                'address_number' => '100',
                'address_neighborhood' => 'Cajazeiras',
                'address_zip' => '41340000',
                'address_reference' => self::MARK_ABUSO,
                'property_polygon_geojson' => self::poligonoAbuso(),
                'is_virtual_office' => false,
                'is_public_area' => false,
                'has_independent_access' => false,
            ]);
        }
    }

    /**
     * Roda a detecção de abuso REAL com o toggle habilitado SÓ durante o seed e
     * restaurado ao final (degradação honesta — a feature nasce OFF). O
     * AbuseDetectionService gera os abuse_alerts de verdade (dedup idempotente do
     * alerta aberto) e encaminha à malha fina acima do limiar. Restaura o valor
     * original do parâmetro (não força OFF por cima de uma escolha do admin).
     *
     * O cache do Settings é invalidado MANUALMENTE: o DatabaseSeeder roda sob
     * WithoutModelEvents, então o observer saved() do Parameter (que normalmente
     * faz o Cache::forget) fica mudo — sem o forget explícito, o toggle ficaria
     * cacheado ON após o seed.
     */
    private function detectarAbusoReal(): void
    {
        $toggle = Parameter::query()->where('key', 'features.deteccao_abuso')->first();

        if ($toggle === null) {
            $this->command?->warn('AuditoriaDevSeeder: parâmetro features.deteccao_abuso ausente — detecção não executada (degradação honesta).');

            return;
        }

        $original = $toggle->value;
        $cacheKey = 'sile.parameters.features.deteccao_abuso';

        try {
            $toggle->forceFill(['value' => '1'])->save();
            Cache::forget($cacheKey);
            app(AbuseDetectionService::class)->detectar();
        } finally {
            $toggle->forceFill(['value' => $original])->save();
            Cache::forget($cacheKey);
        }
    }

    /**
     * Registra um acesso a DADO PESSOAL pelo fluxo REAL (HU-102): o mesmo call
     * site do ProcessoController@show (log_name 'analise', event 'consulta-processo',
     * personal_data=true), com um gestor como causer — alimenta a métrica do
     * painel LGPD. Idempotente: registra o acesso dedicado uma única vez.
     *
     * @param  AuditoriaDevContexto  $ctx
     */
    private function registrarAcessoDadoPessoal(array $ctx): void
    {
        $processo = ViabilityRequest::query()->where('address_reference', self::MARK_DECISAO)->first();

        if ($processo === null) {
            return;
        }

        $jaRegistrado = Activity::query()
            ->where('event', 'consulta-processo')
            ->where('subject_type', $processo->getMorphClass())
            ->where('subject_id', $processo->id)
            ->where('personal_data', true)
            ->exists();

        if ($jaRegistrado) {
            return;
        }

        // setUser (sem login/sessão) define o causer para o AuditService; o
        // forgetGuards ao final restaura o estado de auth do processo do seed.
        Auth::setUser($ctx['gestor']);

        try {
            app(AuditService::class)->log(
                logName: 'analise',
                event: 'consulta-processo',
                description: "Consulta do processo #{$processo->id}",
                properties: [
                    'viability_request_id' => $processo->id,
                    'protocol_number' => $processo->protocol_number,
                ],
                subject: $processo,
                personalData: true,
            );
        } finally {
            Auth::forgetGuards();
        }
    }

    /**
     * Leva o processo a em_analise pelo caminho legítimo: protocola pelo serviço
     * real e transiciona protocolada→em_analise pela máquina de estados. Em pgsql
     * o gatilho do expresso pode já ter encaminhado (sem zona → em_analise);
     * nesse caso a transição é pulada e o estado respeitado. Degrada honesto se
     * não chegar a em_analise.
     */
    private function levarParaEmAnalise(ViabilityRequest $request, User $requerente, User $analista): ?ViabilityRequest
    {
        if ($request->status === ViabilityRequestStatus::Rascunho) {
            app(ProtocolarSolicitacaoService::class)->protocol($request, $requerente);
            $request = $request->fresh();
        }

        if ($request->status === ViabilityRequestStatus::Protocolada) {
            app(ViabilityRequestStateMachine::class)->transition(
                $request,
                ViabilityRequestStatus::EmAnalise,
                $analista,
                reason: 'Encaminhado à análise técnica (exemplo de explicabilidade do EP12)',
                publicLabel: ViabilityRequestStatus::EmAnalise->publicLabel(),
            );
            $request = $request->fresh();
        }

        return $request->status === ViabilityRequestStatus::EmAnalise ? $request : null;
    }

    /**
     * Cria (ou reusa) um rascunho instruído (empresa, imóvel/polígono, área, CNAE
     * principal e o anexo de fachada — cobre o requisito-base do protocolo real).
     * Idempotente pelo marcador em address_reference.
     *
     * @param  AuditoriaDevContexto  $ctx
     */
    private function rascunhoInstruido(
        array $ctx,
        User $requerente,
        Company $empresa,
        string $marker,
        string $street,
        string $number,
        string $neighborhood,
        string $zip,
        array $poligono,
    ): ViabilityRequest {
        $existing = ViabilityRequest::query()->where('address_reference', $marker)->first();

        if ($existing !== null) {
            return $existing;
        }

        $request = ViabilityRequest::factory()->draft()->create([
            'origin' => ViabilityRequestOrigin::Portal,
            'service_type_id' => $ctx['type']->id,
            'company_id' => $empresa->id,
            'requester_user_id' => $requerente->id,
            'created_by_user_id' => $requerente->id,
            'used_area_m2' => 120.0,
            'property_registration' => null,
            'address_street' => $street,
            'address_number' => $number,
            'address_neighborhood' => $neighborhood,
            'address_zip' => $zip,
            'address_reference' => $marker,
            'property_polygon_geojson' => $poligono,
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
            'sha256' => hash('sha256', "auditoria-fachada-{$request->id}"),
            'uploaded_by_user_id' => $requerente->id,
        ]);

        return $request;
    }

    /**
     * Cria (ou reusa) um requerente dedicado de dev (papel cidadão, e-mail
     * verificado, termo LGPD aceito). Idempotente por e-mail.
     */
    private function requerente(string $email, string $name, string $cpf): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'cpf' => $cpf, 'phone' => null, 'password' => 'password'],
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->assignRole('cidadao');

        if ($term = LegalTerm::current('lgpd')) {
            LegalTermAcceptance::firstOrCreate(
                ['user_id' => $user->id, 'legal_term_id' => $term->id],
                ['ip_address' => '127.0.0.1', 'accepted_at' => now()],
            );
        }

        return $user;
    }

    /**
     * Cria (ou reusa) uma empresa dedicada de dev. Idempotente por CNPJ.
     */
    private function empresa(string $cnpj, string $legalName, string $tradeName): Company
    {
        return Company::firstOrCreate(
            ['cnpj' => $cnpj],
            [
                'legal_name' => $legalName,
                'trade_name' => $tradeName,
                'legal_nature_code' => '2062',
                'legal_nature' => 'Sociedade Empresária Limitada',
                'size_code' => '01',
                'size' => 'ME',
                'street' => 'Rua de Exemplo',
                'number' => 's/n',
                'neighborhood' => 'Centro',
                'city' => 'Salvador',
                'state' => 'BA',
                'zip_code' => '40000000',
                'source' => CompanySource::Manual,
            ],
        );
    }

    private function vincular(Company $empresa, User $requerente): void
    {
        CompanyUser::firstOrCreate(
            ['company_id' => $empresa->id, 'user_id' => $requerente->id, 'role' => CompanyLinkRole::Responsavel],
            ['started_at' => now()],
        );
    }

    /**
     * Polígono do padrão de abuso (Cajazeiras) — distinto dos demais exemplos.
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    private static function poligonoAbuso(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.4205, -12.9005],
                [-38.4205, -12.8995],
                [-38.4195, -12.8995],
                [-38.4195, -12.9005],
                [-38.4205, -12.9005],
            ]],
        ];
    }

    /**
     * Polígono do processo decidido (Itapuã) — FORA da zona fictícia (Centro):
     * o motor degrada honesto a em_analise, abrindo caminho à análise técnica.
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    private static function poligonoDecisao(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.3605, -12.9505],
                [-38.3605, -12.9495],
                [-38.3595, -12.9495],
                [-38.3595, -12.9505],
                [-38.3605, -12.9505],
            ]],
        ];
    }

    /**
     * Polígono da decisão legada (Brotas) — distinto de todos os demais.
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    private static function poligonoLegado(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.4805, -12.9805],
                [-38.4805, -12.9795],
                [-38.4795, -12.9795],
                [-38.4795, -12.9805],
                [-38.4805, -12.9805],
            ]],
        ];
    }
}
