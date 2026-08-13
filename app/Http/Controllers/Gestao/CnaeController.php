<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RiscoMunicipal;
use App\Enums\RiscoSanitario;
use App\Enums\RuleDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreCnaeRequest;
use App\Http\Requests\Gestao\StoreRiscoCondicionanteRequest;
use App\Http\Requests\Gestao\UpdateCnaeRequest;
use App\Http\Requests\Gestao\UpdateRiscoCondicionanteRequest;
use App\Models\Cnae;
use App\Models\RiskClassification;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\CnaesReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Ficha única do CNAE (06-08bis): cadastro do CNAE, classificação de risco
 * municipal (Decreto 32.636/2020) e perguntas de condicionante sanitária
 * (VISA) numa só tela — substitui /gestao/risco e /gestao/risco/condicionantes.
 * O grau de risco municipal é salvo direto (updateOrCreate na linha vigente,
 * sem quatro olhos): a publicação em lote com quatro olhos continua existindo
 * só no import oficial da planilha (RiscoMunicipalImportService), não nesta tela.
 */
class CnaeController extends Controller
{
    /**
     * Colunas ordenáveis e tamanhos de página aceitos via request —
     * whitelists técnicas de proteção; o padrão de página é parâmetro.
     */
    private const SORTABLE_COLUMNS = ['code', 'description'];

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    /**
     * Listagem com busca por código (prefixo, dígitos) ou denominação
     * (HU-011 CA-01), filtro de situação, ordenação e itens por página
     * server-driven (Fase 2.4). Paginação parametrizada — nenhum valor
     * de negócio hardcoded.
     */
    public function index(Request $request): Response|HttpResponse
    {
        // HU-131/RN-009: com ?formato=, exporta o conjunto filtrado da tela pelo
        // contrato único — sem rota nova, sem reimplementar export.
        if (in_array($request->string('formato')->lower()->toString(), ['csv', 'xlsx', 'pdf'], true)) {
            return app(ReportExporter::class)->export(
                app(CnaesReportSource::class),
                ReportFilters::fromArray($request->only(['search', 'active', 'sort', 'direction'])),
                $request->string('formato')->lower()->toString(),
                $request->user(),
            );
        }

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'code';

        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $active = $request->string('active')->toString();

        $cnaes = Cnae::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();
                $digits = preg_replace('/\D/', '', $term);

                $query->where(function ($inner) use ($term, $digits) {
                    if ($digits !== '') {
                        $inner->where('code', 'like', "{$digits}%");
                    }

                    // whereLike sem case: LIKE do PostgreSQL é case-sensitive.
                    $inner->orWhereLike('description', "%{$term}%", caseSensitive: false);
                });
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active === '1'))
            ->orderBy($sort, $direction)
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Cnae $cnae) => [
                'id' => $cnae->id,
                'code' => $cnae->code,
                'formatted_code' => $cnae->formatted_code,
                'description' => $cnae->description,
                'active' => $cnae->active,
                'class_code' => $cnae->class_code,
            ]);

        return Inertia::render('gestao/cnaes/index', [
            'cnaes' => $cnaes,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'active' => in_array($active, ['0', '1'], true) ? $active : '',
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('gestao/cnaes/criar', [
            'niveisMunicipais' => $this->niveisMunicipais(),
        ]);
    }

    /**
     * Ficha única do CNAE: dados próprios + classificação de risco municipal
     * vigente + perguntas de condicionante sanitária vigentes.
     */
    public function edit(Cnae $cnae): Response
    {
        $municipal = $this->versaoMunicipalVigente();
        $sanitaria = $this->versaoSanitariaVigente();

        $classificacao = $municipal === null ? null : RiskClassification::query()
            ->where('rule_version_id', $municipal->id)
            ->where('cnae_code', $cnae->code)
            ->first();

        $condicionantes = $sanitaria === null ? collect() : RiskCondicionante::query()
            ->where('rule_version_id', $sanitaria->id)
            ->where('cnae_code', $cnae->code)
            ->orderBy('id')
            ->get();

        return Inertia::render('gestao/cnaes/editar', [
            'cnae' => [
                'id' => $cnae->id,
                'code' => $cnae->code,
                'formatted_code' => $cnae->formatted_code,
                'description' => $cnae->description,
                'active' => $cnae->active,
                'exige_rt' => $cnae->exige_rt,
                'exige_rt_se_alto' => $cnae->exige_rt_se_alto,
                'exige_fator_multiplicador' => $cnae->exige_fator_multiplicador,
                'exige_detalhamento_multiplicador' => $cnae->exige_detalhamento_multiplicador,
                'risco_municipal' => $classificacao?->risco_municipal->value,
            ],
            'condicionantes' => $condicionantes->map(fn (RiskCondicionante $condicionante) => [
                'id' => $condicionante->id,
                'pergunta' => $condicionante->pergunta,
                'regra_reclassificacao' => $condicionante->regra_reclassificacao,
                'texto_parecer' => $condicionante->texto_parecer,
            ])->values(),
            'niveisMunicipais' => $this->niveisMunicipais(),
            'niveisReclassificacao' => $this->niveisReclassificacao(),
            'semVersaoMunicipal' => $municipal === null,
            'semVersaoSanitaria' => $sanitaria === null,
        ]);
    }

    public function store(StoreCnaeRequest $request): RedirectResponse
    {
        $municipal = $this->versaoMunicipalVigente();

        if ($municipal === null) {
            return back()->withInput()->with(
                'error',
                'Não há versão vigente de risco municipal para registrar a classificação.',
            );
        }

        $validated = $request->validated();
        $riscoMunicipal = $validated['risco_municipal'];
        unset($validated['risco_municipal']);

        $cnae = Cnae::create($validated);

        RiskClassification::updateOrCreate(
            ['rule_version_id' => $municipal->id, 'cnae_code' => $cnae->code],
            ['risco_municipal' => $riscoMunicipal],
        );

        return redirect()->route('gestao.cnaes.index')->with('status', 'CNAE cadastrado com sucesso.');
    }

    public function update(UpdateCnaeRequest $request, Cnae $cnae): RedirectResponse
    {
        $municipal = $this->versaoMunicipalVigente();

        if ($municipal === null) {
            return back()->with(
                'error',
                'Não há versão vigente de risco municipal para registrar a classificação.',
            );
        }

        $validated = $request->validated();
        $riscoMunicipal = $validated['risco_municipal'];
        unset($validated['risco_municipal']);

        $cnae->update($validated);

        RiskClassification::updateOrCreate(
            ['rule_version_id' => $municipal->id, 'cnae_code' => $cnae->code],
            ['risco_municipal' => $riscoMunicipal],
        );

        return back()->with('status', 'CNAE atualizado com sucesso.');
    }

    /**
     * Exclusão física bloqueada quando o CNAE está vinculado a empresas
     * (Fase 3): verificação amigável na aplicação + restrictOnDelete como
     * defesa no banco (company_cnae.cnae_id).
     */
    public function destroy(Cnae $cnae): RedirectResponse
    {
        if ($cnae->companies()->exists()) {
            return back()->with('error', 'CNAE vinculado a empresas não pode ser excluído.');
        }

        $cnae->delete();

        return back()->with('status', 'CNAE excluído.');
    }

    /**
     * Cadastro de pergunta de condicionante sanitária diretamente na ficha do
     * CNAE — reaproveita o request do antigo RiscoCondicionanteController,
     * ignorando qualquer cnae_code enviado: o vínculo vem sempre da rota.
     */
    public function storeCondicionante(StoreRiscoCondicionanteRequest $request, Cnae $cnae): RedirectResponse
    {
        $versao = $this->versaoSanitariaVigente();

        if ($versao === null) {
            return back()->with('error', 'Não há versão vigente de risco sanitário para vincular a condicionante.');
        }

        RiskCondicionante::create([
            'rule_version_id' => $versao->id,
            'cnae_code' => $cnae->code,
            ...$request->safe()->except('cnae_code'),
        ]);

        return back()->with('status', 'Pergunta de classificação de risco cadastrada com sucesso.');
    }

    public function updateCondicionante(
        UpdateRiscoCondicionanteRequest $request,
        Cnae $cnae,
        RiskCondicionante $condicionante,
    ): RedirectResponse {
        if ($condicionante->cnae_code !== $cnae->code) {
            abort(404);
        }

        $condicionante->update($request->safe()->except('cnae_code'));

        return back()->with('status', 'Pergunta atualizada com sucesso.');
    }

    public function destroyCondicionante(Cnae $cnae, RiskCondicionante $condicionante): RedirectResponse
    {
        if ($condicionante->cnae_code !== $cnae->code) {
            abort(404);
        }

        $condicionante->delete();

        return back()->with('status', 'Pergunta removida.');
    }

    private function versaoMunicipalVigente(): ?RuleVersion
    {
        return RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first();
    }

    private function versaoSanitariaVigente(): ?RuleVersion
    {
        return RuleVersion::vigente(RuleDomain::RiscoSanitario)->first();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function niveisMunicipais(): array
    {
        return array_map(
            fn (RiscoMunicipal $nivel) => ['value' => $nivel->value, 'label' => $nivel->label()],
            RiscoMunicipal::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function niveisReclassificacao(): array
    {
        return array_map(
            fn (RiscoSanitario $nivel) => ['value' => $nivel->value, 'label' => $nivel->label()],
            RiscoSanitario::cases(),
        );
    }
}
