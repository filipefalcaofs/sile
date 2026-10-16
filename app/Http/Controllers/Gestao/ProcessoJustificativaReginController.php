<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\AtualizarJustificativaReginRequest;
use App\Models\ViabilityRequest;
use App\Services\Regin\JustificativaReginService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Edição da justificativa do parecer à JUCEB na tela do processo. Controller
 * fino: valida e delega ao JustificativaReginService. Gated por concluir-analise.
 */
class ProcessoJustificativaReginController extends Controller
{
    public function __construct(private JustificativaReginService $justificativa) {}

    public function __invoke(AtualizarJustificativaReginRequest $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        try {
            $this->justificativa->salvar($viabilityRequest, $request->validated('justificativa'));
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['justificativa' => $e->getMessage()]);
        }

        return back()->with('status', 'Justificativa salva.');
    }
}
