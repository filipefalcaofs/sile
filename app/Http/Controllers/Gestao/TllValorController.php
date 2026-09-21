<?php

namespace App\Http\Controllers\Gestao;

use App\Exceptions\FourEyesViolationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\PropagacaoTllRequest;
use App\Http\Requests\Gestao\TllValorRequest;
use App\Models\TllValor;
use App\Services\Analise\TllPropagacaoExercicio;
use App\Services\Analise\TllPublicacaoExercicio;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD administrável da tabela de valores TLL por exercício (HU-071/HU-014) —
 * dado versionado que o TllCalculoService usa ao calcular o DAM (RN-004) e que
 * alimenta o bloco `taxas` enviado à SEFAZ. O administrador cria/edita/ativa-
 * inativa o valor pela retaguarda; a chave (código TLL, exercício) é única e a
 * inativação preserva o histórico (sem destroy). Listagem server-driven
 * espelhando o HolidayController; a auditoria (RN-002) é automática via
 * HasAuditoria do model. Gated por manter-parametros (reuso — como feriados;
 * sem permissão nova); o 403 é auditado no ponto único (bootstrap/app.php).
 */
class TllValorController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const DEFAULT_PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;

        $active = $request->string('active')->toString();
        $exercicio = $request->string('exercicio')->toString();

        $valores = TllValor::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();

                $query->where(function ($q) use ($term): void {
                    $q->whereLike('codigo_tll', "%{$term}%", caseSensitive: false)
                        ->orWhereLike('servico_sefaz', "%{$term}%", caseSensitive: false);
                });
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active === '1'))
            ->when($exercicio !== '' && ctype_digit($exercicio), fn ($query) => $query->where('exercicio', (int) $exercicio))
            ->orderByDesc('exercicio')
            ->orderBy('codigo_tll')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TllValor $valor): array => [
                'id' => $valor->id,
                'codigo_tll' => $valor->codigo_tll,
                'exercicio' => $valor->exercicio,
                'valor' => (string) $valor->valor,
                'taxa_servico' => (string) $valor->taxa_servico,
                'codigo_tll_sefaz' => $valor->codigo_tll_sefaz,
                'codigo_servico_sefaz' => $valor->codigo_servico_sefaz,
                'servico_sefaz' => $valor->servico_sefaz,
                'active' => $valor->active,
            ]);

        return Inertia::render('gestao/tll/index', [
            'valores' => $valores,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
                'active' => in_array($active, ['0', '1'], true) ? $active : '',
                'exercicio' => $exercicio,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function store(TllValorRequest $request): RedirectResponse
    {
        TllValor::create($request->validated());

        return back()->with('status', 'Valor de TLL cadastrado com sucesso.');
    }

    public function update(TllValorRequest $request, TllValor $tllValor): RedirectResponse
    {
        $tllValor->update($request->validated());

        return back()->with('status', 'Valor de TLL atualizado com sucesso.');
    }

    /**
     * Liga/desliga o valor. NUNCA exclui: inativar preserva o histórico e tira
     * o valor do cálculo do DAM sem perder o registro auditado.
     */
    public function toggleActivation(TllValor $tllValor): RedirectResponse
    {
        $tllValor->update(['active' => ! $tllValor->active]);

        return back()->with('status', $tllValor->active
            ? 'Valor de TLL reativado.'
            : 'Valor de TLL inativado.');
    }

    public function gerarExercicio(PropagacaoTllRequest $request): RedirectResponse
    {
        try {
            app(TllPropagacaoExercicio::class)->propagar(
                (int) $request->validated('exercicio_origem'),
                (int) $request->validated('exercicio_destino'),
                (string) $request->validated('fator'),
                (string) $request->validated('decreto'),
                (int) $request->user()->id,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Exercício gerado em rascunho. Revise e publique com um segundo usuário.');
    }

    public function publicarExercicio(Request $request, int $exercicio): RedirectResponse
    {
        try {
            app(TllPublicacaoExercicio::class)->publicar($exercicio, (int) $request->user()->id);
        } catch (FourEyesViolationException|DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Exercício {$exercicio} publicado.");
    }
}
