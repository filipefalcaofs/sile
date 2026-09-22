<?php

namespace Database\Seeders;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\DocumentRequirement;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Analise\DistribuicaoService;
use App\Services\Analise\PendenciaService;
use App\Services\Solicitacao\DocumentRequirementResolver;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\DemoMode;
use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Fechamento do EP11: torna a comunicação multicanal e a central de notificações
 * NAVEGÁVEIS no desenvolvimento com dados REAIS — não linhas fabricadas. Cria um
 * processo de exemplo DEDICADO (marcador próprio, separado dos exemplos da Fase
 * 8/9/10 já asseridos), leva-o a em_analise pelo CAMINHO LEGÍTIMO (protocolo +
 * transição da máquina de estados + atribuição ao analista@sile.dev) e executa o
 * FLUXO REAL do ciclo de pendência:
 *
 *  1. PendenciaService::abrir → em_pendencia + dispara PendenciaSolicitada; o
 *     listener AUTO-DESCOBERTO NotificarPendencia roteia pelo NotificationDispatcher
 *     e cria as communications (email/in_app) + a notificação in-app do requerente.
 *  2. PendenciaService::responder → reabre (em_analise) + dispara PendenciaRespondida;
 *     NotificarRespostaPendencia avisa o analista (in-app + ledger).
 *
 * Dados FICTÍCIOS, lógica REAL (entrega-funcional): as comunicações nascem do
 * fluxo de verdade, com o ledger honesto (HU-096) e o canal database nativo. O
 * WhatsApp permanece OFF (degradação honesta — provedor real é Fase 13).
 *
 * Gate de ambiente: NUNCA em produção (igual aos demais seeders dev). Diferente
 * do AnaliseDevSeeder, NÃO exige PostGIS — o ciclo de pendência é territorial-
 * agnóstico, então roda também em SQLite (a central/histórico ficam navegáveis).
 * Idempotente: marcador estável em address_reference + guarda pela existência da
 * pendência (re-seed não duplica processo, comunicações nem notificações). Roda
 * após o AnaliseDevSeeder; depende de SectorSeeder (analista@sile.dev + setor),
 * CompanySeeder, catálogos e dos usuários dev.
 */
class ComunicacaoDevSeeder extends Seeder
{
    public const MARK = 'Comunicação dev — pendência multicanal (in-app + e-mail)';

    public function run(): void
    {
        // GATE DE AMBIENTE (anti-fachada): exemplos de dev só em dev/teste.
        if (! DemoMode::allowsDemoSeeders()) {
            $this->command?->warn('ComunicacaoDevSeeder: ignorado fora de dev/teste.');

            return;
        }

        $ctx = $this->contexto();

        if ($ctx === null) {
            $this->command?->warn('ComunicacaoDevSeeder: pré-requisitos ausentes (cidadão/empresa/tipo/CNAE/fachada/setor/analista) — exemplo não criado.');

            return;
        }

        // Idempotência: se o exemplo dedicado já passou pelo ciclo (tem pendência),
        // o seed está completo — nada a duplicar (comunicações/notificações estáveis).
        $existente = $this->exemploExistente($ctx['cidadao']);

        if ($existente !== null && AnalysisPendency::query()->where('viability_request_id', $existente->id)->exists()) {
            return;
        }

        $request = $this->emAnaliseAtribuido($ctx);

        if ($request === null) {
            $this->command?->warn('ComunicacaoDevSeeder: não foi possível levar o exemplo a em_analise — exemplo não concluído (degradação honesta).');

            return;
        }

        $this->abrirEResponderPendencia($request, $ctx['analista']);
    }

    /**
     * Leva o exemplo dedicado a em_analise pelo caminho legítimo: protocola pelo
     * serviço real (número + timeline) e transiciona protocolada→em_analise pela
     * MÁQUINA DE ESTADOS (o mesmo mecanismo que o FluxoExpressoService usa por
     * dentro — auditada + timeline). Em dev pgsql com fila síncrona o gatilho do
     * expresso pode já ter encaminhado (sem zona → em_analise): nesse caso a
     * transição é pulada e o estado é respeitado. Depois coloca na caixa do setor
     * e o analista ASSUME (HU-081), deixando o processo pronto para a pendência.
     *
     * @param  ComunicacaoDevContexto  $ctx
     */
    private function emAnaliseAtribuido(array $ctx): ?ViabilityRequest
    {
        $request = $this->exampleDraft($ctx);

        if ($request->status === ViabilityRequestStatus::Rascunho) {
            app(ProtocolarSolicitacaoService::class)->protocol($request, $ctx['cidadao']);
            $request = $request->fresh();
        }

        if ($request->status === ViabilityRequestStatus::Protocolada) {
            app(ViabilityRequestStateMachine::class)->transition(
                $request,
                ViabilityRequestStatus::EmAnalise,
                $ctx['analista'],
                reason: 'Encaminhado à análise técnica (exemplo de comunicação do EP11)',
                publicLabel: ViabilityRequestStatus::EmAnalise->publicLabel(),
            );
            $request = $request->fresh();
        }

        // Degradação honesta: se não chegou a em_analise, não força o estado.
        if ($request->status !== ViabilityRequestStatus::EmAnalise) {
            return null;
        }

        // Caixa do setor de triagem + atribuição ao analista (HU-080/081). O
        // roteamento automático ao setor é pendência SEDUR; no dev a caixa é a de
        // triagem (igual ao AnaliseDevSeeder). forceFill: sector_id fora do fillable.
        if ($request->sector_id === null) {
            $request->forceFill(['sector_id' => $ctx['sector']->id])->save();
        }

        if ($request->assigned_user_id === null) {
            app(DistribuicaoService::class)->assumir($request->fresh(), $ctx['analista']);
        }

        return $request->fresh();
    }

    /**
     * Executa o ciclo REAL de pendência: o analista abre (notifica o requerente
     * multicanal) e o requerente responde (reabre a análise + notifica o analista).
     * As comunicações e as notificações in-app nascem dos listeners de verdade.
     */
    private function abrirEResponderPendencia(ViabilityRequest $request, User $analista): void
    {
        $pendencia = app(PendenciaService::class)->abrir(
            $request,
            $analista,
            'Anexe a planta de situação atualizada e o comprovante de uso do imóvel.',
        );

        app(PendenciaService::class)->responder(
            $pendencia,
            'Planta de situação e comprovante de uso anexados conforme solicitado.',
        );
    }

    /**
     * Cria (ou reusa) o rascunho dedicado num imóvel FORA de zona (Rio Vermelho):
     * sem zona oficial, o motor degrada honesto a em_analise em dev pgsql — e a
     * transição legítima cobre os ambientes em que o gatilho do expresso não roda
     * (fila assíncrona, ou job fakeado nos testes). FKs explícitos preservam as
     * contagens do seed. Idempotente pelo marcador.
     *
     * @param  ComunicacaoDevContexto  $ctx
     */
    private function exampleDraft(array $ctx): ViabilityRequest
    {
        $existing = $this->exemploExistente($ctx['cidadao']);

        if ($existing !== null) {
            return $existing;
        }

        $request = ViabilityRequest::factory()->draft()->create([
            'origin' => ViabilityRequestOrigin::Portal,
            'service_type_id' => $ctx['type']->id,
            'company_id' => $ctx['company']->id,
            'requester_user_id' => $ctx['cidadao']->id,
            'created_by_user_id' => $ctx['cidadao']->id,
            'used_area_m2' => 90.0,
            'address_street' => 'Rua da Paciência',
            'address_number' => '120',
            'address_neighborhood' => 'Rio Vermelho',
            'address_zip' => '41940000',
            'address_reference' => self::MARK,
            'property_polygon_geojson' => self::poligonoRioVermelho(),
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
            'sha256' => hash('sha256', "comunicacao-fachada-{$request->id}"),
            'uploaded_by_user_id' => $ctx['cidadao']->id,
        ]);

        return $request;
    }

    private function exemploExistente(User $cidadao): ?ViabilityRequest
    {
        return ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('address_reference', self::MARK)
            ->first();
    }

    /**
     * Pré-requisitos do seed (cidadão/empresa/tipo/CNAE/fachada + setor/analista do
     * SectorSeeder). Devolve null se faltar algo (degradação honesta).
     *
     * @phpstan-type ComunicacaoDevContexto array{cidadao: User, company: Company, type: ViabilityServiceType, cnae: Cnae, fachada: DocumentRequirement, sector: Sector, analista: User}
     *
     * @return ComunicacaoDevContexto|null
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

        if ($cidadao === null || $company === null || $type === null || $cnae === null
            || $fachada === null || $sector === null || $analista === null) {
            return null;
        }

        return compact('cidadao', 'company', 'type', 'cnae', 'fachada', 'sector', 'analista');
    }

    /**
     * Polígono no Rio Vermelho — FORA de zona oficial: sem zona, o motor degrada
     * honestamente para em_analise (coerente com a transição legítima do seed).
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    private static function poligonoRioVermelho(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.4895, -13.0105],
                [-38.4895, -13.0095],
                [-38.4885, -13.0095],
                [-38.4885, -13.0105],
                [-38.4895, -13.0105],
            ]],
        ];
    }
}
