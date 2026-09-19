<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RuleDomain;
use App\Http\Controllers\Controller;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoPergunta;
use App\Models\TratamentoRegra;
use App\Support\Audit\AuditService;
use Inertia\Inertia;
use Inertia\Response;

class TratamentoRegrasController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(): Response
    {
        $vigente = RuleVersion::vigente(RuleDomain::RiscoTratamento)->first();

        $perguntas = $vigente === null
            ? []
            : TratamentoPergunta::query()
                ->where('rule_version_id', $vigente->getKey())
                ->orderBy('numero')
                ->get(['numero', 'texto', 'referencia'])
                ->all();

        $contagens = [
            'cnaes' => $vigente === null ? 0 : TratamentoEnquadramento::query()->where('rule_version_id', $vigente->getKey())->distinct()->count('cnae'),
            'enquadramentos' => $vigente === null ? 0 : TratamentoEnquadramento::query()->where('rule_version_id', $vigente->getKey())->count(),
            'perguntas' => count($perguntas),
            'regras' => $vigente === null ? 0 : TratamentoRegra::query()->where('rule_version_id', $vigente->getKey())->count(),
            'bindings' => $vigente === null ? 0 : TratamentoCnaeBinding::query()->where('rule_version_id', $vigente->getKey())->count(),
        ];

        $this->audit->log(
            logName: 'tratamento',
            event: 'consulta-planilha',
            description: 'Consulta da planilha de tratamento vigente',
            properties: [
                'versao' => $vigente?->version,
                'contagens' => $contagens,
            ],
            result: 'sucesso',
            rulesVersion: $vigente?->version,
        );

        return Inertia::render('gestao/tratamento/index', [
            'versao' => $vigente?->version,
            'contagens' => $contagens,
            'perguntas' => $perguntas,
        ]);
    }
}
