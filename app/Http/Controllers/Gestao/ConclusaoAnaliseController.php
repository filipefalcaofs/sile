<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\ConclusionOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ConcluirAnaliseRequest;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\ConclusaoAnaliseService;
use App\Services\Analise\DivergenciaConclusaoException;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Tramitação "Conclusão de Análise". Controller fino: valida o resultado e
 * delega ao ConclusaoAnaliseService. Gated por analisar-processos.
 */
class ConclusaoAnaliseController extends Controller
{
    public function __construct(private ConclusaoAnaliseService $conclusao) {}

    public function __invoke(ConcluirAnaliseRequest $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        $ator = $request->user();

        abort_if(! $ator instanceof User, 401, 'É preciso estar autenticado para concluir a análise.');

        $resultado = ConclusionOutcome::from($request->validated('resultado'));

        try {
            $this->conclusao->concluir(
                $viabilityRequest,
                $ator,
                $resultado,
                $request->validated('parecer'),
                exigirFichaSalva: true,
                confirmarDivergencia: $request->boolean('confirmar_divergencia'),
                validade: $request->validadeDeferimento(),
            );
        } catch (DivergenciaConclusaoException $e) {
            throw ValidationException::withMessages(['divergencia' => $e->getMessage()]);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['conclusao' => $e->getMessage()]);
        }

        return back()->with('status', 'Análise concluída: '.$resultado->label().'.');
    }
}
