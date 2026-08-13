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
use App\Services\Solicitacao\CancelarSolicitacaoService;
use App\Services\Solicitacao\DocumentRequirementResolver;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Solicitações de viabilidade de EXEMPLO para o cidadão de desenvolvimento
 * (cidadao@sile.dev), cobrindo os estados navegáveis da Fase 8: rascunho
 * instruído (pronto para protocolar), protocolada (número/timeline REAIS),
 * cancelada e contingência (canal de operador).
 *
 * Dados FICTÍCIOS, lógica REAL (entrega-funcional): o protocolo passa pelo
 * ProtocolarSolicitacaoService e o cancelamento pelo CancelarSolicitacaoService
 * — número único, transições e auditoria genuínos, exatamente como em produção.
 * Os metadados do anexo de fachada são de exemplo (o upload oficial grava os
 * bytes reais); aqui basta o requisito coberto para o resolver real liberar o
 * protocolo — sem o anexo, o exemplo bloquearia, como na aplicação.
 *
 * Depende de CompanySeeder (empresas do cidadão), CnaeSeeder (atividade real),
 * ViabilityServiceTypeSeeder e DocumentRequirementSeeder. Roda por último no
 * DatabaseSeeder. Idempotente: cada exemplo tem um marcador estável em
 * address_reference; em re-seed reusa o existente (nada é duplicado, nenhum
 * número novo é gerado). Nunca executar em produção com este cidadão de teste.
 */
class SolicitacaoDevSeeder extends Seeder
{
    private const MARK_RASCUNHO = 'Exemplo dev — rascunho instruído';

    private const MARK_PROTOCOLADA = 'Exemplo dev — protocolada';

    private const MARK_CANCELADA = 'Exemplo dev — cancelada';

    private const MARK_CONTINGENCIA = 'Exemplo dev — contingência';

    public function run(): void
    {
        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->first();

        $company = $cidadao === null ? null : Company::query()
            ->whereHas('links', fn ($query) => $query->where('user_id', $cidadao->id)->whereNull('ended_at'))
            ->orderBy('id')
            ->first();

        $type = ViabilityServiceType::query()->where('code', 'viabilidade-1-estabelecimento')->first()
            ?? ViabilityServiceType::query()->where('active', true)->orderBy('id')->first();

        // CNAE 4712-1/00 (minimercado): risco baixo real (Decreto 32.636/2020) e
        // enquadramento por área no Quadro 7 — exemplo coerente com os motores.
        $cnae = Cnae::query()->where('code', '4712100')->first()
            ?? Cnae::query()->where('active', true)->orderBy('code')->first();

        $fachada = DocumentRequirement::query()->where('code', DocumentRequirementResolver::CODE_FACHADA)->first();

        // Degradação honesta: sem os pré-requisitos (cidadão/empresa/tipo/CNAE/
        // requisito de fachada) não há o que instruir — registra o bloqueio e
        // sai, NUNCA cria um exemplo pela metade (anti-fachada).
        if ($cidadao === null || $company === null || $type === null || $cnae === null || $fachada === null) {
            $this->command?->warn('SolicitacaoDevSeeder: pré-requisitos ausentes (cidadão/empresa/tipo/CNAE/fachada) — exemplos não criados.');

            return;
        }

        $operador = User::query()->where('email', 'admin@sile.dev')->first() ?? $cidadao;

        // 1) Rascunho instruído — pronto para protocolar (também serve de alvo do
        //    comando de evidência solicitacao:protocolar).
        $this->exampleDraft($cidadao, $cidadao, $company, $type, $cnae, $fachada, self::MARK_RASCUNHO);

        // 2) Protocolada — instrui e PROTOCOLA pelo serviço real (número/timeline).
        $protocolada = $this->exampleDraft($cidadao, $cidadao, $company, $type, $cnae, $fachada, self::MARK_PROTOCOLADA);
        $this->protocolar($protocolada, $cidadao);

        // 3) Cancelada — protocola e CANCELA pelo serviço real (transição + motivo).
        $cancelada = $this->exampleDraft($cidadao, $cidadao, $company, $type, $cnae, $fachada, self::MARK_CANCELADA);
        $this->protocolar($cancelada, $cidadao);
        $this->cancelar($cancelada, $cidadao);

        // 4) Contingência — operador "em nome de" o cidadão; MESMA máquina/motor.
        $contingencia = $this->exampleDraft($cidadao, $operador, $company, $type, $cnae, $fachada, self::MARK_CONTINGENCIA, [
            'origin' => ViabilityRequestOrigin::Contingencia,
            'contingency_reason' => 'Integrador Regin indisponível — registro por contingência (HU-148).',
        ]);
        $this->protocolar($contingencia, $operador);
    }

    /**
     * Cria (ou reusa) um rascunho instruído de exemplo: empresa, imóvel/polígono,
     * área, CNAE principal e o anexo de fachada (cobre o requisito-base para o
     * protocolo real liberar). Idempotente pelo marcador em address_reference.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function exampleDraft(
        User $cidadao,
        User $creator,
        Company $company,
        ViabilityServiceType $type,
        Cnae $cnae,
        DocumentRequirement $fachada,
        string $marker,
        array $overrides = [],
    ): ViabilityRequest {
        $existing = ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('address_reference', $marker)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // Factory ->draft() inicializa o status em memória (a coluna tem default
        // de banco, mas o create() cru não o hidrata). Os FKs explícitos evitam
        // criar empresa/usuário/tipo extras (preserva as contagens do seed).
        $request = ViabilityRequest::factory()->draft()->create(array_merge([
            'origin' => ViabilityRequestOrigin::Portal,
            'service_type_id' => $type->id,
            'company_id' => $company->id,
            'requester_user_id' => $cidadao->id,
            'created_by_user_id' => $creator->id,
            'used_area_m2' => 120.0,
            'address_street' => 'Avenida Sete de Setembro',
            'address_number' => '1000',
            'address_neighborhood' => 'Centro',
            'address_zip' => '40060000',
            'address_reference' => $marker,
            'property_polygon_geojson' => self::poligono(),
            'is_virtual_office' => false,
            'is_public_area' => false,
            'has_independent_access' => true,
        ], $overrides));

        $request->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $request->documents()->create([
            'requirement_id' => $fachada->id,
            'disk' => (string) Settings::get('storage.documentos.disk', config('sile.storage.documentos.disk', 'local')),
            'path' => "solicitacoes/seed/{$request->id}-fachada.jpg",
            'original_name' => 'fachada-exemplo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 51200,
            'sha256' => hash('sha256', "fachada-exemplo-{$request->id}"),
            'uploaded_by_user_id' => $creator->id,
        ]);

        return $request;
    }

    /**
     * Protocola o exemplo pelo serviço real (número único + transição auditada).
     * Idempotente: já protocolado/cancelado em re-seed → não gera número novo.
     */
    private function protocolar(ViabilityRequest $request, User $actor): void
    {
        if ($request->status !== ViabilityRequestStatus::Rascunho) {
            return;
        }

        app(ProtocolarSolicitacaoService::class)->protocol($request, $actor);
    }

    /**
     * Cancela o exemplo pelo serviço real (transição protocolada→cancelada com
     * motivo). Idempotente: já cancelado em re-seed → não faz nada.
     */
    private function cancelar(ViabilityRequest $request, User $actor): void
    {
        if ($request->status === ViabilityRequestStatus::Cancelada) {
            return;
        }

        app(CancelarSolicitacaoService::class)->cancel(
            $request,
            $actor,
            'Cancelada a pedido do requerente (exemplo de desenvolvimento).',
        );
    }

    /**
     * Polígono de 4 pontos (quadrilátero fechado) em Salvador — fonte portável; a
     * geometry derivada é gravada via ST_* só no pgsql (irrelevante ao protocolo).
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    private static function poligono(): array
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
}
