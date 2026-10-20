<?php

namespace App\Services\Renovacao;

use App\Enums\AnalysisCategory;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\Sector;
use App\Models\ServiceDeclaration;
use App\Models\ServiceDeclarationAcceptance;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Analise\EnviarParaAnaliseService;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Services\Solicitacao\ProtocolNumberGenerator;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Abre e protocola a renovação de um TVL provisório. Copia endereço e
 * atividades da origem (não são editáveis), completa o território como no
 * protocolo comum (inscrição, CODLOG, zona, via, endereçamento e lote) e
 * manda o processo para análise semi-expressa já com a ficha do motor.
 * Não passa pelo deferimento automático.
 */
class RenovacaoProtocoloService
{
    public function __construct(
        private RenovacaoElegibilidadeService $elegibilidade,
        private ProtocolNumberGenerator $numeros,
        private ViabilityRequestStateMachine $stateMachine,
        private AuditService $audit,
        private ProtocolarSolicitacaoService $protocoloViabilidade,
        private EnviarParaAnaliseService $enviarAnalise,
    ) {}

    /**
     * @param  array{email?: string|null, phone?: string|null, mobile?: string|null}  $contato
     * @param  list<int>  $declaracaoIds
     */
    public function protocolar(ViabilityRequest $origem, User $ator, array $contato, array $declaracaoIds): ViabilityRequest
    {
        $avaliacao = $this->elegibilidade->avaliar($origem);

        if (! $avaliacao->elegivel) {
            throw new DomainException($avaliacao->motivo ?? 'Esta viabilidade não pode ser renovada.');
        }

        $origem->loadMissing(['cnaes', 'company']);

        return $this->protocolarComum(
            atributos: [
                'renews_viability_request_id' => $origem->id,
                'company_id' => $origem->company_id,
                'used_area_m2' => $origem->used_area_m2,
                'property_registration' => $origem->property_registration,
                'address_street' => $origem->address_street,
                'address_number' => $origem->address_number,
                'address_complement' => $origem->address_complement,
                'address_neighborhood' => $origem->address_neighborhood,
                'address_zip' => $origem->address_zip,
                'address_reference' => $origem->address_reference,
                'property_polygon_geojson' => $origem->property_polygon_geojson,
            ],
            cnaes: $origem->cnaes
                ->map(fn ($cnae): array => ['id' => $cnae->id, 'is_primary' => (bool) $cnae->pivot->is_primary])
                ->all(),
            empresa: $origem->company,
            ator: $ator,
            contato: $contato,
            declaracaoIds: $declaracaoIds,
            auditoria: [
                'description' => fn (ViabilityRequest $renovacao): string => "Renovação da viabilidade locacional da solicitação #{$origem->id} protocolada como #{$renovacao->id}",
                'properties' => ['origem_id' => $origem->id],
            ],
        );
    }

    /**
     * Parte comum do protocolo: declarações, criação do processo, número,
     * transições, auditoria, território, setor de triagem e envio à análise.
     * O verificador, quando informado, roda dentro da transação antes de criar
     * qualquer coisa.
     *
     * @param  array<string, mixed>  $atributos
     * @param  list<array{id: int, is_primary: bool}>  $cnaes
     * @param  array{email?: string|null, phone?: string|null, mobile?: string|null}  $contato
     * @param  list<int>  $declaracaoIds
     * @param  array{description: callable, properties: array<string, mixed>}  $auditoria
     */
    private function protocolarComum(
        array $atributos,
        array $cnaes,
        ?Company $empresa,
        User $ator,
        array $contato,
        array $declaracaoIds,
        array $auditoria,
        ?callable $verificar = null,
    ): ViabilityRequest {
        $tipo = ViabilityServiceType::query()->where('code', 'renovacao-tvl')->first();

        if ($tipo === null) {
            throw new DomainException('O tipo de serviço Renovação de viabilidade locacional não está cadastrado.');
        }

        $obrigatorias = ServiceDeclaration::vigentesPara($tipo->id)
            ->where('required', true);

        $aceitas = collect($declaracaoIds)->map(fn (int|string $id): int => (int) $id);

        if ($obrigatorias->contains(fn (ServiceDeclaration $declaracao): bool => ! $aceitas->contains($declaracao->id))) {
            throw new DomainException('Marque todas as declarações de responsabilidade para protocolar a renovação.');
        }

        $renovacao = DB::transaction(function () use ($atributos, $cnaes, $empresa, $ator, $contato, $auditoria, $verificar, $tipo, $obrigatorias): ViabilityRequest {
            if ($verificar !== null) {
                $verificar();
            }

            $renovacao = ViabilityRequest::query()->create([
                'origin' => ViabilityRequestOrigin::Portal,
                'service_type_id' => $tipo->id,
                'requester_user_id' => $ator->id,
                'created_by_user_id' => $ator->id,
                ...$atributos,
            ]);

            $renovacao->refresh();

            foreach ($cnaes as $cnae) {
                $renovacao->cnaes()->attach($cnae['id'], [
                    'is_primary' => $cnae['is_primary'],
                ]);
            }

            foreach ($obrigatorias as $declaracao) {
                ServiceDeclarationAcceptance::query()->create([
                    'service_declaration_id' => $declaracao->id,
                    'viability_request_id' => $renovacao->id,
                    'user_id' => $ator->id,
                    'version' => $declaracao->version,
                    'accepted_at' => now(),
                ]);
            }

            $this->atualizarContato($empresa, $contato);

            $renovacao->forceFill([
                'protocol_number' => $this->numeros->generate(),
                'protocoled_at' => now(),
            ])->save();

            $this->stateMachine->transition(
                $renovacao,
                ViabilityRequestStatus::Protocolada,
                $ator,
                publicLabel: ViabilityRequestStatus::Protocolada->publicLabel(),
            );

            $this->stateMachine->transition(
                $renovacao,
                ViabilityRequestStatus::EmAnalise,
                $ator,
                reason: 'Renovação de viabilidade locacional provisória encaminhada à análise semi-expressa.',
                publicLabel: ViabilityRequestStatus::EmAnalise->publicLabel(),
            );

            $renovacao->forceFill([
                'analysis_category' => AnalysisCategory::SemiExpresso,
            ])->save();

            $this->audit->log(
                logName: 'solicitacoes',
                event: 'renovacao_protocolada',
                description: ($auditoria['description'])($renovacao),
                properties: [
                    ...$auditoria['properties'],
                    'renovacao_id' => $renovacao->id,
                    'protocol_number' => $renovacao->protocol_number,
                    'mobile' => $contato['mobile'] ?? null,
                ],
                subject: $renovacao,
            );

            return $renovacao;
        });

        // Fora da transação, como no protocolo comum: SEFAZ ou GIS fora do ar
        // não desfaz o número. O motor só roda depois, para a ficha enxergar
        // zona, via e polígono atuais.
        $this->protocoloViabilidade->completarTerritorio($renovacao);

        $setorId = $this->setorTriagemId();

        if ($setorId !== null) {
            $renovacao->forceFill(['sector_id' => $setorId])->save();
        }

        $this->enviarAnalise->enviar(
            $renovacao,
            $ator,
            'Renovação de viabilidade locacional encaminhada à análise.',
        );

        return $renovacao->fresh();
    }

    /**
     * Mesma caixa de entrada do encaminhamento que não é expresso. Sem
     * parâmetro, ou com setor inativo, o processo segue sem caixa.
     */
    private function setorTriagemId(): ?int
    {
        $id = Settings::get('analise.setor_triagem_id');

        if ($id === null || $id === '') {
            return null;
        }

        $id = (int) $id;

        return Sector::query()->whereKey($id)->where('active', true)->exists()
            ? $id
            : null;
    }

    /**
     * @param  array{email?: string|null, phone?: string|null, mobile?: string|null}  $contato
     */
    private function atualizarContato(?Company $empresa, array $contato): void
    {
        if ($empresa === null) {
            return;
        }

        $dados = [];

        if (filled($contato['email'] ?? null)) {
            $dados['email'] = $contato['email'];
        }

        if (filled($contato['phone'] ?? null)) {
            $dados['phone'] = $contato['phone'];
        }

        if ($dados !== []) {
            $empresa->fill($dados)->save();
        }
    }
}
