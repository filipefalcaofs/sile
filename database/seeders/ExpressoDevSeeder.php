<?php

namespace Database\Seeders;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\DocumentRequirement;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Solicitacao\DocumentRequirementResolver;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Exemplos NAVEGÁVEIS do fluxo expresso para o cidadão de desenvolvimento
 * (cidadao@sile.dev), provando o core value de ponta a ponta com LÓGICA REAL:
 *
 *  1. DEFERIMENTO — solicitação sobre a ZONA FICTÍCIA (Centro), CNAE de risco
 *     baixo (minimercado 4712-1/00 → expresso): o motor REAL defere e gera o
 *     número TVL. É o caminho que demonstra o deferimento automático.
 *  2. EM ANÁLISE — solicitação FORA da zona fictícia (Pituba): sem zona, o motor
 *     degrada HONESTAMENTE para em_analise (nenhuma decisão, nenhum TVL). É a
 *     prova anti-fachada da degradação que vale em produção até a zona oficial.
 *
 * Dados FICTÍCIOS, lógica REAL (entrega-funcional): protocola pelo
 * ProtocolarSolicitacaoService e decide pelo FluxoExpressoService — número,
 * timeline, decisão imutável e auditoria genuínos, exatamente como em produção.
 *
 * Gate de ambiente: NUNCA roda em produção (igual à ZonaFicticiaDevSeeder).
 * Driver-aware: a DECISÃO reexecuta os motores territoriais (PostGIS) — só
 * decide em pgsql; em SQLite os exemplos ficam protocolados (a decisão real é
 * exercida nos testes @group postgis), consistente com a Fase 8. Idempotente:
 * cada exemplo tem um marcador estável em address_reference. Depende de
 * CompanySeeder, CnaeSeeder, catálogos e da ZonaFicticiaDevSeeder (antes dele).
 */
class ExpressoDevSeeder extends Seeder
{
    public const MARK_DEFERIDA = 'Exemplo expresso dev — deferimento (zona fictícia)';

    public const MARK_EM_ANALISE = 'Exemplo expresso dev — em análise (sem zona)';

    public function run(): void
    {
        // GATE DE AMBIENTE: exemplos decididos sobre a zona fictícia só em
        // dev/teste — produção mantém a degradação honesta (em_analise).
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('ExpressoDevSeeder: ignorado fora de dev/teste.');

            return;
        }

        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->first();

        $company = $cidadao === null ? null : Company::query()
            ->whereHas('links', fn ($query) => $query->where('user_id', $cidadao->id)->whereNull('ended_at'))
            ->orderBy('id')
            ->first();

        $type = ViabilityServiceType::query()->where('code', 'viabilidade-1-estabelecimento')->first()
            ?? ViabilityServiceType::query()->where('active', true)->orderBy('id')->first();

        // Minimercado (4712-1/00): risco baixo real (Decreto 32.636/2020 → fluxo
        // expresso) e enquadramento por área no Quadro 7 — coerente com ZCN-1.
        $cnae = Cnae::query()->where('code', '4712100')->first()
            ?? Cnae::query()->where('active', true)->orderBy('code')->first();

        $fachada = DocumentRequirement::query()->where('code', DocumentRequirementResolver::CODE_FACHADA)->first();

        if ($cidadao === null || $company === null || $type === null || $cnae === null || $fachada === null) {
            $this->command?->warn('ExpressoDevSeeder: pré-requisitos ausentes (cidadão/empresa/tipo/CNAE/fachada) — exemplos não criados.');

            return;
        }

        // 1) Deferimento navegável: dentro da zona fictícia (Centro).
        $deferida = $this->exampleDraft($cidadao, $company, $type, $cnae, $fachada, self::MARK_DEFERIDA, self::poligonoCentro());
        $this->protocolarEDecidir($deferida);

        // 2) Em análise honesto: fora da zona fictícia (Pituba) — sem zona.
        $emAnalise = $this->exampleDraft($cidadao, $company, $type, $cnae, $fachada, self::MARK_EM_ANALISE, self::poligonoPituba());
        $this->protocolarEDecidir($emAnalise);
    }

    /**
     * Cria (ou reusa) um rascunho instruído de exemplo com o polígono informado.
     * Idempotente pelo marcador em address_reference.
     */
    private function exampleDraft(
        User $cidadao,
        Company $company,
        ViabilityServiceType $type,
        Cnae $cnae,
        DocumentRequirement $fachada,
        string $marker,
        array $poligono,
    ): ViabilityRequest {
        $existing = ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('address_reference', $marker)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $request = ViabilityRequest::factory()->draft()->create([
            'origin' => ViabilityRequestOrigin::Portal,
            'service_type_id' => $type->id,
            'company_id' => $company->id,
            'requester_user_id' => $cidadao->id,
            'created_by_user_id' => $cidadao->id,
            'used_area_m2' => 120.0,
            'address_street' => 'Avenida Sete de Setembro',
            'address_number' => '2000',
            'address_neighborhood' => 'Centro',
            'address_zip' => '40060000',
            'address_reference' => $marker,
            'property_polygon_geojson' => $poligono,
            'is_virtual_office' => false,
            'is_public_area' => false,
            'has_independent_access' => true,
        ]);

        $request->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $request->documents()->create([
            'requirement_id' => $fachada->id,
            'disk' => (string) Settings::get('storage.documentos.disk', config('sile.storage.documentos.disk', 'local')),
            'path' => "solicitacoes/seed/{$request->id}-fachada.jpg",
            'original_name' => 'fachada-exemplo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 51200,
            'sha256' => hash('sha256', "expresso-fachada-{$request->id}"),
            'uploaded_by_user_id' => $cidadao->id,
        ]);

        return $request;
    }

    /**
     * Protocola pelo serviço real e, em pgsql, roda a decisão real (o motor
     * reexecuta os motores territoriais — exige PostGIS). Idempotente: já
     * protocolada não reprotocola; já decidida (status != protocolada) o motor
     * trata como no-op. Em SQLite o exemplo fica protocolado (a decisão real é
     * exercida nos testes @group postgis), consistente com a Fase 8.
     */
    private function protocolarEDecidir(ViabilityRequest $request): void
    {
        if ($request->status === ViabilityRequestStatus::Rascunho) {
            app(ProtocolarSolicitacaoService::class)->protocol($request, $request->requester ?? $request->createdBy);
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if ($request->fresh()->status === ViabilityRequestStatus::Protocolada) {
            app(FluxoExpressoService::class)->decide($request->fresh());
        }
    }

    /**
     * Polígono do Centro de Salvador — DENTRO da zona fictícia (ZonaFicticiaDevSeeder).
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    private static function poligonoCentro(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.5108, -12.9711],
                [-38.5108, -12.9709],
                [-38.5106, -12.9709],
                [-38.5106, -12.9711],
                [-38.5108, -12.9711],
            ]],
        ];
    }

    /**
     * Polígono na Pituba — FORA da zona fictícia: sem zona, o motor degrada
     * honestamente para em_analise (prova anti-fachada).
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    private static function poligonoPituba(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.4590, -12.9945],
                [-38.4590, -12.9935],
                [-38.4580, -12.9935],
                [-38.4580, -12.9945],
                [-38.4590, -12.9945],
            ]],
        ];
    }
}
