<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ConsultarLegadoRequest;
use App\Http\Requests\Portal\LookupCnpjRequest;
use App\Http\Requests\Portal\ProtocolarRenovacaoRequest;
use App\Models\Cidadao;
use App\Models\Company;
use App\Models\ServiceDeclaration;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Legado\LegadoIndisponivelException;
use App\Services\Legado\LegadoViabilidadeClient;
use App\Services\Legado\TvlLegadaDetalhe;
use App\Services\Renovacao\BuscaRenovacao;
use App\Services\Renovacao\EnderecoImovelIndisponivelException;
use App\Services\Renovacao\EnderecoImovelNaoEncontradoException;
use App\Services\Renovacao\ProvisoriasLegadasService;
use App\Services\Renovacao\RenovacaoElegibilidadeService;
use App\Services\Renovacao\RenovacaoLegadaElegibilidadeService;
use App\Services\Renovacao\RenovacaoProtocoloService;
use App\Services\Renovacao\ResolvedorEnderecoImovel;
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
        private ResolvedorEnderecoImovel $enderecoImovel,
    ) {}

    public function index(Request $request, BuscaRenovacao $busca): Response
    {
        $termo = $request->query('busca');
        $termo = is_string($termo) && trim($termo) !== '' ? trim($termo) : null;
        $resultado = $termo === null
            ? ['itens' => [], 'indisponivel' => false, 'termo_invalido' => false]
            : $busca->buscar($termo);

        return Inertia::render('portal/renovacao/index', [
            'busca' => $termo,
            'itens' => $resultado['itens'],
            'indisponivel' => $resultado['indisponivel'],
            'termo_invalido' => $resultado['termo_invalido'],
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
                    'numero' => ViabilityDecision::semPrefixo($origem->decision?->tvl_product_number),
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

    public function showLegado(Request $request, string $cnpj, string $numero): Response|RedirectResponse
    {
        return $this->abrirLegado($request, $cnpj, $numero);
    }

    public function showLegadoSemCnpj(Request $request, string $numero): Response|RedirectResponse
    {
        return $this->abrirLegado($request, null, $numero);
    }

    private function abrirLegado(Request $request, ?string $cnpjDaRota, string $numero): Response|RedirectResponse
    {
        try {
            $tvl = $this->legadoClient->tvlPorNumero((int) $numero);

            if ($tvl === null) {
                return $this->voltarAoIndice(self::MENSAGEM_LEGADO_GENERICA);
            }

            $cnpj = ProvisoriasLegadasService::cnpjDaRenovacao($tvl, $cnpjDaRota);

            if ($cnpjDaRota !== null && $cnpj !== $cnpjDaRota) {
                return $this->voltarAoIndice(self::MENSAGEM_LEGADO_GENERICA);
            }

            $titularidade = $cnpjDaRota === null
                ? null
                : $this->elegibilidadeLegada->titularidade($tvl, new Company(['cnpj' => $cnpjDaRota]));
        } catch (LegadoIndisponivelException) {
            return $this->voltarAoIndice('O serviço de consulta de viabilidades anteriores está indisponível. Tente novamente em instantes.');
        }

        if ($titularidade?->consultaIndisponivel) {
            return $this->voltarAoIndice((string) $titularidade->motivo);
        }

        if ($titularidade !== null && ! $titularidade->titular) {
            return $this->voltarAoIndice(self::MENSAGEM_LEGADO_GENERICA);
        }

        $avaliacao = $this->elegibilidadeLegada->avaliarAposTitularidade($tvl);

        if (! $avaliacao->elegivel) {
            return $this->voltarAoIndice((string) $avaliacao->motivo);
        }

        try {
            $enderecoTexto = $this->enderecoLegadoTexto($tvl);
        } catch (EnderecoImovelNaoEncontradoException|EnderecoImovelIndisponivelException $exception) {
            return $this->voltarAoIndice($exception->getMessage());
        }

        $empresa = $cnpj === null ? null : Company::query()->where('cnpj', $cnpj)->orderBy('id')->first();
        $tipo = ViabilityServiceType::query()->where('code', 'renovacao-tvl')->first();

        return Inertia::render('portal/renovacao/wizard', [
            'origem' => [
                'id' => null,
                'legado' => true,
                'numero' => $tvl->numero,
                'cnpj_digitos' => $cnpj,
                'empresa' => $empresa?->legal_name ?? $tvl->razaoSocial,
                'cnpj' => $cnpj === null ? null : (new Company(['cnpj' => $cnpj]))->formatted_cnpj,
                'endereco' => $enderecoTexto,
                'atividades' => collect($tvl->atividades)
                    ->map(fn (array $atividade): string => trim($atividade['codigo'].' '.$atividade['descricao']))
                    ->values(),
                'validade' => $tvl->validade?->toDateString(),
                'tipo' => 'Provisória',
            ],
            'declaracoes' => $this->declaracoes($tipo),
            'contato' => $this->contatoDoUsuario($request),
        ]);
    }

    public function show(Request $request, ViabilityRequest $origem): Response|RedirectResponse
    {
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
                'numero' => ViabilityDecision::semPrefixo($origem->decision?->tvl_product_number),
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
            'contato' => $this->contatoDoUsuario($request),
        ]);
    }

    public function store(ProtocolarRenovacaoRequest $request, ViabilityRequest $origem): RedirectResponse
    {
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

    public function storeLegado(ProtocolarRenovacaoRequest $request, string $cnpj, string $numero): RedirectResponse
    {
        return $this->protocolarLegada($request, $cnpj, $numero);
    }

    public function storeLegadoSemCnpj(ProtocolarRenovacaoRequest $request, string $numero): RedirectResponse
    {
        return $this->protocolarLegada($request, null, $numero);
    }

    private function protocolarLegada(ProtocolarRenovacaoRequest $request, ?string $cnpjDaRota, string $numero): RedirectResponse
    {
        try {
            $tvl = $this->legadoClient->tvlPorNumero((int) $numero);

            if ($tvl === null) {
                return $this->voltarAoIndice(self::MENSAGEM_LEGADO_GENERICA);
            }

            $cnpj = ProvisoriasLegadasService::cnpjDaRenovacao($tvl, $cnpjDaRota);

            if ($cnpjDaRota !== null && $cnpj !== $cnpjDaRota) {
                return $this->voltarAoIndice(self::MENSAGEM_LEGADO_GENERICA);
            }

            if ($cnpjDaRota !== null) {
                $titularidade = $this->elegibilidadeLegada->titularidade($tvl, new Company(['cnpj' => $cnpjDaRota]));

                if ($titularidade->consultaIndisponivel) {
                    return $this->voltarAoIndice((string) $titularidade->motivo);
                }

                if (! $titularidade->titular) {
                    return $this->voltarAoIndice(self::MENSAGEM_LEGADO_GENERICA);
                }
            }

            $renovacao = $this->protocolo->protocolarLegada(
                $tvl,
                $cnpj,
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

    /**
     * Contato pré-preenchido: o de quem está renovando, nunca o da empresa,
     * que qualquer cidadão pode buscar pelo CNPJ. E-mail provisório da conta
     * criada pelo Portal de Serviços não serve de contato.
     *
     * @return array{email: ?string, phone: ?string}
     */
    private function contatoDoUsuario(Request $request): array
    {
        $usuario = $this->usuarioEfetivo($request);
        $email = (string) $usuario->email;

        return [
            'email' => $email === '' || str_ends_with($email, '.viabiliza.local') ? null : $email,
            'phone' => $usuario->phone,
        ];
    }

    /**
     * @return list<array{id: int, body: string, required: bool}>
     */
    private function declaracoes(?ViabilityServiceType $tipo): array
    {
        return $tipo === null
            ? []
            : ServiceDeclaration::vigentesPara($tipo->id)->map(fn (ServiceDeclaration $declaracao): array => [
                'id' => $declaracao->id,
                'body' => $declaracao->body,
                'required' => $declaracao->required,
            ])->values()->all();
    }

    private function usuarioEfetivo(Request $request): Cidadao
    {
        return app(CurrentRepresentation::class)->effectiveUser($request->user());
    }

    /**
     * @return list<int>
     */
    private function empresasDo(Cidadao $usuario): array
    {
        return app(CurrentRepresentation::class)->companyIds($usuario);
    }

    /**
     * Endereço do imóvel pela inscrição da TVL; sem inscrição, o do legado.
     *
     * @throws EnderecoImovelNaoEncontradoException
     * @throws EnderecoImovelIndisponivelException
     */
    private function enderecoLegadoTexto(TvlLegadaDetalhe $tvl): string
    {
        if ($this->enderecoImovel->temInscricao($tvl->inscricaoImobiliaria)) {
            return $this->enderecoImovel->resolver($tvl->inscricaoImobiliaria)->texto();
        }

        return collect([
            $tvl->endereco['logradouro'],
            $tvl->endereco['numero'],
            $tvl->endereco['bairro'],
            $tvl->endereco['cep'],
        ])->filter()->implode(', ');
    }

    private function voltarAoIndice(string $mensagem): RedirectResponse
    {
        return redirect()->route('portal.renovacao.index')->with('error', $mensagem);
    }
}
