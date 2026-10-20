<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ConsultarLegadoRequest;
use App\Http\Requests\Portal\LookupCnpjRequest;
use App\Http\Requests\Portal\ProtocolarRenovacaoRequest;
use App\Models\Company;
use App\Models\ServiceDeclaration;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Legado\LegadoIndisponivelException;
use App\Services\Legado\LegadoViabilidadeClient;
use App\Services\Renovacao\ProvisoriasLegadasService;
use App\Services\Renovacao\RenovacaoElegibilidadeService;
use App\Services\Renovacao\RenovacaoLegadaElegibilidadeService;
use App\Services\Renovacao\RenovacaoProtocoloService;
use App\Services\Sedur\SedurContatoClient;
use App\Services\Sedur\SedurContatoIndisponivelException;
use App\Services\Sedur\SedurContatoNaoEncontradoException;
use App\Support\Representation\CurrentRepresentation;
use DomainException;
use Illuminate\Http\JsonResponse;
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
    private const MENSAGEM_LEGADO_GENERICA = 'Esta viabilidade não pôde ser carregada para esta empresa.';

    public function __construct(
        private RenovacaoElegibilidadeService $elegibilidade,
        private RenovacaoProtocoloService $protocolo,
        private SedurContatoClient $contato,
        private ProvisoriasLegadasService $legado,
        private LegadoViabilidadeClient $legadoClient,
        private RenovacaoLegadaElegibilidadeService $elegibilidadeLegada,
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

        $legado = $this->legado->paraEmpresas(
            Company::query()->whereIn('id', $ids)->orderBy('id')->get(),
        );

        return Inertia::render('portal/renovacao/index', [
            'tvls' => $tvls,
            'legado' => $legado['itens'],
            'legado_indisponivel' => $legado['indisponivel'],
        ]);
    }

    public function consultarCnpj(LookupCnpjRequest $request): JsonResponse
    {
        $cnpj = preg_replace('/\D/', '', (string) $request->validated('cnpj')) ?? '';

        if (strlen($cnpj) !== 14) {
            return response()->json([
                'message' => 'Informe um CNPJ numérico de 14 dígitos.',
            ], 422);
        }

        try {
            $empresa = $this->contato->consultarCnpj($cnpj);
        } catch (SedurContatoNaoEncontradoException) {
            return response()->json([
                'message' => 'CNPJ não encontrado na base da SEDUR.',
            ], 404);
        } catch (SedurContatoIndisponivelException) {
            return response()->json([
                'message' => 'A consulta cadastral da SEDUR está indisponível agora. Tente de novo em instantes.',
            ], 503);
        }

        $usuario = $this->usuarioEfetivo($request);
        $empresasVinculadas = Company::query()
            ->where('cnpj', $cnpj)
            ->whereIn('id', $this->empresasDo($usuario))
            ->get();
        $empresas = $empresasVinculadas->pluck('id');

        $viabilidades = ViabilityRequest::query()
            ->whereIn('company_id', $empresas)
            ->where('status', ViabilityRequestStatus::Deferida)
            ->whereHas('decision', fn ($query) => $query->whereNotNull('tvl_product_number'))
            ->with('decision')
            ->orderBy('id')
            ->get()
            ->map(function (ViabilityRequest $origem): array {
                $avaliacao = $this->elegibilidade->avaliar($origem);

                return [
                    'id' => $origem->id,
                    'numero' => $origem->decision?->tvl_product_number,
                    'elegivel' => $avaliacao->elegivel,
                    'motivo' => $avaliacao->motivo,
                ];
            })
            ->values();

        return response()->json([
            'cnpj' => $empresa->cnpj,
            'razao_social' => $empresa->razaoSocial,
            'nome_fantasia' => $empresa->nomeFantasia,
            'telefone' => $empresa->telefone,
            'endereco' => $empresa->endereco,
            'viabilidades' => $viabilidades,
            'legado' => $empresasVinculadas->isEmpty() ? [] : $this->legado->paraEmpresas($empresasVinculadas)['itens'],
        ]);
    }

    public function consultarLegado(ConsultarLegadoRequest $request): JsonResponse
    {
        $empresa = Company::query()->findOrFail((int) $request->validated('empresa_id'));

        return response()->json(
            $this->legado->buscarPorNumero($empresa, (string) $request->validated('numero_viabilidade')),
        );
    }

    public function showLegado(Request $request, int $empresa, string $numero): Response|RedirectResponse
    {
        $this->autorizarEmpresaId($request, $empresa);
        $empresaModelo = Company::query()->findOrFail($empresa);

        try {
            $tvl = $this->legadoClient->tvlPorNumero((int) $numero);

            if ($tvl === null) {
                return $this->voltarAoIndice(self::MENSAGEM_LEGADO_GENERICA);
            }

            $titularidade = $this->elegibilidadeLegada->titularidade($tvl, $empresaModelo);
        } catch (LegadoIndisponivelException) {
            return $this->voltarAoIndice('O serviço de consulta de viabilidades anteriores está indisponível. Tente novamente em instantes.');
        }

        if ($titularidade->consultaIndisponivel) {
            return $this->voltarAoIndice((string) $titularidade->motivo);
        }

        if (! $titularidade->titular) {
            return $this->voltarAoIndice(self::MENSAGEM_LEGADO_GENERICA);
        }

        $avaliacao = $this->elegibilidadeLegada->avaliarAposTitularidade($tvl);

        if (! $avaliacao->elegivel) {
            return $this->voltarAoIndice((string) $avaliacao->motivo);
        }

        $tipo = ViabilityServiceType::query()->where('code', 'renovacao-tvl')->first();

        return Inertia::render('portal/renovacao/wizard', [
            'origem' => [
                'id' => null,
                'legado' => true,
                'numero' => $tvl->numero,
                'empresa_id' => $empresaModelo->id,
                'empresa' => $empresaModelo->legal_name,
                'cnpj' => $empresaModelo->formatted_cnpj,
                'endereco' => collect([
                    $tvl->endereco['logradouro'],
                    $tvl->endereco['numero'],
                    $tvl->endereco['bairro'],
                    $tvl->endereco['cep'],
                ])->filter()->implode(', '),
                'atividades' => collect($tvl->atividades)
                    ->map(fn (array $atividade): string => trim($atividade['codigo'].' '.$atividade['descricao']))
                    ->values(),
                'validade' => $tvl->validade?->toDateString(),
                'tipo' => 'Provisória',
            ],
            'declaracoes' => $tipo === null
                ? []
                : ServiceDeclaration::vigentesPara($tipo->id)->map(fn (ServiceDeclaration $declaracao): array => [
                    'id' => $declaracao->id,
                    'body' => $declaracao->body,
                    'required' => $declaracao->required,
                ])->values(),
            'contato' => [
                'email' => $empresaModelo->email,
                'phone' => $empresaModelo->phone,
            ],
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

    public function storeLegado(ProtocolarRenovacaoRequest $request, int $empresa, string $numero): RedirectResponse
    {
        $this->autorizarEmpresaId($request, $empresa);
        $empresaModelo = Company::query()->findOrFail($empresa);

        try {
            $tvl = $this->legadoClient->tvlPorNumero((int) $numero);

            if ($tvl === null) {
                return $this->voltarAoIndice(self::MENSAGEM_LEGADO_GENERICA);
            }

            $titularidade = $this->elegibilidadeLegada->titularidade($tvl, $empresaModelo);

            if ($titularidade->consultaIndisponivel) {
                return $this->voltarAoIndice((string) $titularidade->motivo);
            }

            if (! $titularidade->titular) {
                return $this->voltarAoIndice(self::MENSAGEM_LEGADO_GENERICA);
            }

            $renovacao = $this->protocolo->protocolarLegada(
                $tvl,
                $empresaModelo,
                $this->usuarioEfetivo($request),
                $request->safe()->only(['email', 'phone', 'mobile']),
                $request->validated('declaration_ids'),
            );
        } catch (LegadoIndisponivelException) {
            return back()->with('error', 'O serviço de consulta de viabilidades anteriores está indisponível. Tente novamente em instantes.');
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('portal.solicitacoes.show', $renovacao)
            ->with('status', 'Renovação protocolada. O processo foi encaminhado para análise.');
    }

    private function usuarioEfetivo(Request $request): User
    {
        return app(CurrentRepresentation::class)->effectiveUser($request->user());
    }

    /**
     * @return list<int>
     */
    private function empresasDo(User $usuario): array
    {
        return app(CurrentRepresentation::class)->companyIds($usuario);
    }

    private function voltarAoIndice(string $mensagem): RedirectResponse
    {
        return redirect()->route('portal.renovacao.index')->with('error', $mensagem);
    }

    private function autorizarEmpresaId(Request $request, int $empresaId): void
    {
        abort_unless(
            app(CurrentRepresentation::class)->actsForCompany($request->user(), $empresaId),
            403,
        );
    }

    private function autorizarEmpresa(Request $request, ViabilityRequest $origem): void
    {
        abort_unless(
            app(CurrentRepresentation::class)->actsForCompany($request->user(), (int) $origem->company_id),
            403,
        );
    }
}
