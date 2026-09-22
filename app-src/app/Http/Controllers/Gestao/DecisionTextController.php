<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\UpdateDecisionTextRequest;
use App\Models\DecisionText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção dos textos decisórios (Fase 4 — parametrização HU-014): os
 * textos emitidos em documentos oficiais (TVL, parecer, ficha do cidadão)
 * viram dado administrável lido pelo motor via DecisionTextCatalog. SEM
 * create/destroy/toggle — a chave é ligada ao motor (call site no código):
 * uma linha criada pela UI sem call site seria fachada, e apagar uma chave
 * quebraria a emissão do documento. O CRUD gerencia template/description
 * das chaves conhecidas; a edição é auditada (HasAuditoria, RN-002) e tem
 * efeito sem deploy (o model invalida o cache do catálogo na escrita).
 * Atrás de manter-parametros — reuso da permissão dos textos-padrão.
 */
class DecisionTextController extends Controller
{
    /**
     * Rótulos amigáveis dos grupos (prefixo da chave). Prefixo sem rótulo
     * mapeado aparece com o próprio prefixo — visível, nunca escondido.
     *
     * @var array<string, string>
     */
    private const GRUPOS = [
        'base_legal' => 'Bases legais',
        'louos' => 'LOUOS — enquadramento locacional',
        'consulta' => 'Consulta pública de viabilidade',
        'analise' => 'Análise técnica',
        'justificativa' => 'Justificativa fundamentada',
        'explicacao' => 'Explicação da decisão',
    ];

    /**
     * Lista completa agrupada por prefixo da chave, sem paginação: são os
     * ~40 textos conhecidos (novos entram via motor + seed, nunca pela tela).
     * A description carrega o contrato de placeholders — o admin precisa
     * dele antes de editar o template.
     */
    public function index(): Response
    {
        $ordem = array_keys(self::GRUPOS);

        $grupos = DecisionText::query()
            ->orderBy('key')
            ->get()
            ->groupBy(fn (DecisionText $texto) => Str::before($texto->key, '.'))
            ->map(fn ($textos, string $prefixo) => [
                'prefixo' => $prefixo,
                'label' => self::GRUPOS[$prefixo] ?? $prefixo,
                'textos' => $textos->map(fn (DecisionText $texto) => [
                    'key' => $texto->key,
                    'template' => $texto->template,
                    'description' => $texto->description,
                ])->values()->all(),
            ])
            ->sortBy(fn (array $grupo) => ($pos = array_search($grupo['prefixo'], $ordem, true)) === false ? PHP_INT_MAX : $pos)
            ->values()
            ->all();

        return Inertia::render('gestao/textos-decisao/index', [
            'grupos' => $grupos,
        ]);
    }

    /**
     * Atualiza template e descrição. A key é imutável (não consta no
     * UpdateRequest, logo o valor enviado é descartado — padrão CPF/CNAE) e
     * o binding é por ela ({decisionText:key}): chave desconhecida é 404.
     */
    public function update(UpdateDecisionTextRequest $request, DecisionText $decisionText): RedirectResponse
    {
        $decisionText->update($request->validated());

        return back()->with('status', 'Texto decisório atualizado com sucesso.');
    }
}
