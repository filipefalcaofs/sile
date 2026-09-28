<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ProtocolarRenovacaoRequest;
use App\Models\CompanyUser;
use App\Models\ServiceDeclaration;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Renovacao\RenovacaoElegibilidadeService;
use App\Services\Renovacao\RenovacaoProtocoloService;
use App\Support\Representation\CurrentRepresentation;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renovação de TVL provisório emitido por este portal (o que o requisito
 * chamava de origem Simplifica). A lista mostra os TVLs das empresas do
 * cidadão que existem aqui, elegíveis ou com o motivo do bloqueio. TVL de
 * origem SIGS não entra: a renovação dele continua no SIGS.
 */
class RenovacaoController extends Controller
{
    public function __construct(
        private RenovacaoElegibilidadeService $elegibilidade,
        private RenovacaoProtocoloService $protocolo,
    ) {}

    public function index(Request $request): Response
    {
        $usuario = $this->usuarioEfetivo($request);
        $ids = $this->empresasDo($usuario);

        $tvls = ViabilityRequest::query()
            ->whereIn('company_id', $ids)
            ->where('status', ViabilityRequestStatus::Deferida)
            ->whereHas('decision', fn ($query) => $query->whereNotNull('tvl_product_number'))
            ->with(['decision', 'company'])
            ->orderBy('id')
            ->get()
            ->map(function (ViabilityRequest $origem): array {
                $avaliacao = $this->elegibilidade->avaliar($origem);

                return [
                    'id' => $origem->id,
                    'elegivel' => $avaliacao->elegivel,
                    'motivo' => $avaliacao->motivo,
                    'numero' => $origem->decision?->tvl_product_number,
                    'validade' => $origem->decision?->tvl_valid_until?->toDateString(),
                    'tipo' => $origem->decision?->tvl_kind?->label(),
                    'empresa' => $origem->company?->legal_name,
                    'endereco' => $origem->address_street,
                ];
            })
            ->values();

        return Inertia::render('portal/renovacao/index', [
            'tvls' => $tvls,
        ]);
    }

    public function show(Request $request, ViabilityRequest $origem): Response|RedirectResponse
    {
        $this->autorizarEmpresa($request, $origem);

        $avaliacao = $this->elegibilidade->avaliar($origem);

        if (! $avaliacao->elegivel) {
            return redirect()
                ->route('portal.renovacao.index')
                ->with('error', $avaliacao->motivo);
        }

        $origem->loadMissing(['company', 'cnaes', 'decision']);
        $tipo = ViabilityServiceType::query()->where('code', 'renovacao-tvl')->first();

        return Inertia::render('portal/renovacao/wizard', [
            'origem' => [
                'id' => $origem->id,
                'numero' => $origem->decision?->tvl_product_number,
                'empresa' => $origem->company?->legal_name,
                'cnpj' => $origem->company?->formatted_cnpj,
                'endereco' => collect([
                    $origem->address_street,
                    $origem->address_number,
                    $origem->address_neighborhood,
                    $origem->address_zip,
                ])->filter()->implode(', '),
                'atividades' => $origem->cnaes->map(fn ($cnae): string => trim($cnae->formatted_code.' '.$cnae->description))->values(),
                'validade' => $origem->decision?->tvl_valid_until?->toDateString(),
                'tipo' => $origem->decision?->tvl_kind?->label(),
            ],
            'declaracoes' => $tipo === null
                ? []
                : ServiceDeclaration::vigentesPara($tipo->id)->map(fn (ServiceDeclaration $declaracao): array => [
                    'id' => $declaracao->id,
                    'body' => $declaracao->body,
                    'required' => $declaracao->required,
                ])->values(),
            'contato' => [
                'email' => $origem->company?->email,
                'phone' => $origem->company?->phone,
            ],
        ]);
    }

    public function store(ProtocolarRenovacaoRequest $request, ViabilityRequest $origem): RedirectResponse
    {
        $this->autorizarEmpresa($request, $origem);

        try {
            $renovacao = $this->protocolo->protocolar(
                $origem,
                $this->usuarioEfetivo($request),
                $request->safe()->only(['email', 'phone', 'mobile']),
                $request->validated('declaration_ids'),
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('portal.solicitacoes.show', $renovacao)
            ->with('status', 'Renovação protocolada. O processo foi encaminhado para análise.');
    }

    private function usuarioEfetivo(Request $request): User
    {
        return app(CurrentRepresentation::class)->grantor() ?? $request->user();
    }

    /**
     * @return list<int>
     */
    private function empresasDo(User $usuario): array
    {
        return CompanyUser::query()
            ->where('user_id', $usuario->id)
            ->whereNull('ended_at')
            ->pluck('company_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function autorizarEmpresa(Request $request, ViabilityRequest $origem): void
    {
        abort_unless(
            in_array((int) $origem->company_id, $this->empresasDo($this->usuarioEfetivo($request)), true),
            403,
        );
    }
}
