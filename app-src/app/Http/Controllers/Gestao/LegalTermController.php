<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreLegalTermRequest;
use App\Http\Requests\Gestao\UpdateLegalTermRequest;
use App\Models\LegalTerm;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção dos termos legais versionados (LGPD e futuros — parametrização
 * HU-014, item 2.3): dado administrável, não código. Integridade do aceite
 * LGPD: a versão é computada (max+1 por tipo, nunca informada pelo usuário)
 * e o termo PUBLICADO é imutável — sem update, sem destroy — porque o aceite
 * do usuário (LegalTermAcceptance) referencia aquela versão exata; editar
 * seria fraude de registro. Rascunho (published_at null) é editável e
 * excluível. Publicar torna a versão vigente (current()) e preserva os
 * aceites anteriores na versão antiga. Auditoria automática via HasAuditoria
 * no model (RN-002).
 */
class LegalTermController extends Controller
{
    private const MENSAGEM_IMUTAVEL = 'Termos publicados são imutáveis — crie uma nova versão.';

    /**
     * Lista completa, sem paginação: poucos tipos × poucas versões. O status
     * de cada linha é derivado no servidor — vigente (a que current() devolve),
     * rascunho (aguardando publicação) ou substituído (publicado e superado).
     */
    public function index(): Response
    {
        $termos = LegalTerm::query()
            ->orderBy('type')
            ->orderByDesc('version')
            ->get();

        $vigentes = $termos
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->groupBy('type')
            ->map(fn ($grupo) => $grupo->sortByDesc('version')->first()->id);

        return Inertia::render('gestao/termos-legais/index', [
            'termos' => $termos->map(fn (LegalTerm $termo) => [
                'id' => $termo->id,
                'type' => $termo->type,
                'version' => $termo->version,
                'title' => $termo->title,
                'content' => $termo->content,
                'published_at' => $termo->published_at?->format('d/m/Y H:i'),
                'status' => is_null($termo->published_at)
                    ? 'rascunho'
                    : ($vigentes->get($termo->type) === $termo->id ? 'vigente' : 'substituido'),
            ])->values(),
            'tipos' => $termos->pluck('type')->unique()->sort()->values(),
        ]);
    }

    /**
     * Cria rascunho (published_at null) com a próxima versão do tipo —
     * computada, nunca informada pelo usuário. O vigente só muda na
     * publicação explícita.
     */
    public function store(StoreLegalTermRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $proximaVersao = (int) LegalTerm::query()
            ->where('type', $validated['type'])
            ->max('version') + 1;

        LegalTerm::create([
            ...$validated,
            'version' => $proximaVersao,
            'published_at' => null,
        ]);

        return back()->with('status', "Rascunho da versão {$proximaVersao} criado — publique para torná-lo vigente.");
    }

    /**
     * Atualiza título e conteúdo do rascunho. Termo publicado é IMUTÁVEL:
     * o aceite do usuário referencia aquela versão exata — editar seria
     * fraude de registro. O type é imutável (ausente do UpdateRequest).
     */
    public function update(UpdateLegalTermRequest $request, LegalTerm $legalTerm): RedirectResponse
    {
        if (! is_null($legalTerm->published_at)) {
            return back()->with('error', self::MENSAGEM_IMUTAVEL);
        }

        $legalTerm->update($request->validated());

        return back()->with('status', 'Rascunho atualizado com sucesso.');
    }

    /**
     * Publica o rascunho: published_at = agora e current() passa a devolvê-lo.
     * Os aceites anteriores permanecem vinculados à versão aceita (histórico
     * preservado); o middleware re-exige o aceite da nova versão.
     */
    public function publicar(LegalTerm $legalTerm): RedirectResponse
    {
        if (! is_null($legalTerm->published_at)) {
            return back()->with('error', self::MENSAGEM_IMUTAVEL);
        }

        $legalTerm->update(['published_at' => now()]);

        return back()->with('status', "Versão {$legalTerm->version} de \"{$legalTerm->title}\" publicada — vigente imediatamente para novos aceites.");
    }

    /**
     * Exclui APENAS rascunho. O destroy de publicado NUNCA existe: a versão
     * publicada é registro legal referenciado pelos aceites.
     */
    public function destroy(LegalTerm $legalTerm): RedirectResponse
    {
        if (! is_null($legalTerm->published_at)) {
            return back()->with('error', self::MENSAGEM_IMUTAVEL);
        }

        $legalTerm->delete();

        return back()->with('status', 'Rascunho excluído.');
    }
}
