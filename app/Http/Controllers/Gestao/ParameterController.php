<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\UpdateParameterRequest;
use App\Models\Activity;
use App\Models\Parameter;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\ParametrosReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ParameterController extends Controller
{
    /**
     * Catálogo agrupado por domínio (HU-014 CA-01). Valor de sensível nunca
     * sai do servidor (RN-009) — a tela recebe null e exibe placeholder.
     */
    public function index(Request $request): Response|HttpResponse
    {
        // HU-131/RN-009: com ?formato=, exporta o catálogo pelo contrato único —
        // sem rota nova. O catálogo não tem filtros de listagem; RN-009 garante
        // que o valor sensível nunca sai em claro (mascarado no ReportSource).
        if (in_array($request->string('formato')->lower()->toString(), ['csv', 'xlsx', 'pdf'], true)) {
            return app(ReportExporter::class)->export(
                app(ParametrosReportSource::class),
                ReportFilters::fromArray([]),
                $request->string('formato')->lower()->toString(),
                $request->user(),
            );
        }

        $groups = Parameter::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(fn ($parameters) => $parameters->map(fn (Parameter $parameter) => [
                'key' => $parameter->key,
                'type' => $parameter->type,
                'description' => $parameter->description,
                'sensitive' => $parameter->sensitive,
                'requires_connection_test' => $parameter->requires_connection_test,
                'default_value' => $parameter->default_value,
                'value' => $parameter->sensitive ? null : $parameter->value,
                'has_admin_value' => $parameter->getRawOriginal('value') !== null,
                'updated_at' => $parameter->updated_at?->toIso8601String(),
            ])->values());

        return Inertia::render('gestao/parametros/index', [
            'groups' => $groups,
        ]);
    }

    /**
     * Gravação validada pelo catálogo (RN-007) com auditoria explícita de
     * anterior/novo (RN-008) — sensível entra no histórico apenas como o
     * marcador [criptografado] (RN-009). O saved do model invalida o cache:
     * efeito imediato sem deploy (CA-05).
     */
    public function update(UpdateParameterRequest $request, Parameter $parameter): RedirectResponse
    {
        $input = $request->validated('value');

        if ($parameter->sensitive && ($input === null || $input === '')) {
            return back()->with('status', __('Valor mantido.'));
        }

        $old = $parameter->value;
        $parameter->update(['value' => $input]);

        app(AuditService::class)->log(
            'parametros',
            'parametro-alterado',
            "Parâmetro {$parameter->key} alterado",
            [
                'key' => $parameter->key,
                'valor_anterior' => $parameter->sensitive ? '[criptografado]' : $old,
                'valor_novo' => $parameter->sensitive ? '[criptografado]' : $input,
            ],
            $parameter,
        );

        return back()->with('status', __('Parâmetro atualizado com sucesso.'));
    }

    /**
     * Histórico de alterações do parâmetro (CA-07/RN-008): valor anterior,
     * valor novo, responsável e data/hora — sensíveis já entram mascarados
     * na gravação. latest('id') garante ordem estável quando duas alterações
     * caem no mesmo segundo.
     */
    public function history(Parameter $parameter): Response
    {
        $entries = Activity::query()
            ->where('log_name', 'parametros')
            ->where('subject_type', Parameter::class)
            ->where('subject_id', $parameter->id)
            ->with('causer:id,name')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Activity $activity) => [
                'id' => $activity->id,
                'valor_anterior' => $activity->properties['valor_anterior'] ?? null,
                'valor_novo' => $activity->properties['valor_novo'] ?? null,
                'responsavel' => $activity->causer?->name,
                'data' => $activity->created_at->toIso8601String(),
            ]);

        return Inertia::render('gestao/parametros/historico', [
            'parameter' => $parameter->only('key', 'description', 'sensitive'),
            'entries' => $entries,
        ]);
    }
}
