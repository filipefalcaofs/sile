<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Exceptions\FourEyesViolationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\PublicarAnexosEscritorioVirtualRequest;
use App\Models\RuleVersion;
use App\Models\VirtualOfficeActivityCnae;
use App\Services\EscritorioVirtual\EscritorioVirtualAnexosService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Publicação versionada dos Anexos A/B do Decreto 35.062/2021 (escritório
 * virtual) no console SEDUR: upload dos dois CSVs para um rascunho nomeado,
 * preview do diff rascunho × vigente e publicação por quatro olhos. A lista
 * vigente só muda no publish — o motor (permitidoNoAnexo) nunca vê o rascunho.
 * Gate: manter-cnaes (reuso deliberado — os anexos são listas de CNAE, mesmo
 * mantenedor do risco; sem permissão nova).
 */
class EscritorioVirtualAnexosController extends Controller
{
    public function __construct(private EscritorioVirtualAnexosService $service) {}

    public function index(): Response
    {
        $domain = RuleDomain::AtividadesEscritorioVirtual;

        $vigente = RuleVersion::vigente($domain)->with('publisher:id,name')->first();

        $rascunho = RuleVersion::query()
            ->where('domain', $domain->value)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->with('author:id,name')
            ->latest()
            ->first();

        $totaisPorVersao = VirtualOfficeActivityCnae::query()
            ->selectRaw('rule_version_id, anexo, count(*) as total')
            ->groupBy('rule_version_id', 'anexo')
            ->get()
            ->groupBy('rule_version_id');

        $contagens = function (?RuleVersion $versao) use ($totaisPorVersao): array {
            if ($versao === null) {
                return ['anexo_a' => 0, 'anexo_b' => 0];
            }

            $linhas = $totaisPorVersao->get($versao->id, collect());

            return [
                'anexo_a' => (int) ($linhas->firstWhere('anexo', VirtualOfficeActivityCnae::ANEXO_A)?->total ?? 0),
                'anexo_b' => (int) ($linhas->firstWhere('anexo', VirtualOfficeActivityCnae::ANEXO_B)?->total ?? 0),
            ];
        };

        $historico = RuleVersion::query()
            ->where('domain', $domain->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (RuleVersion $versao) use ($contagens): array {
                $contagem = $contagens($versao);

                return [
                    'id' => $versao->id,
                    'version' => $versao->version,
                    'status' => $versao->status->value,
                    'status_label' => $versao->status->label(),
                    'valid_from' => $versao->valid_from?->toDateString(),
                    'valid_to' => $versao->valid_to?->toDateString(),
                    'total' => $contagem['anexo_a'] + $contagem['anexo_b'],
                ];
            })
            ->values()
            ->all();

        return Inertia::render('gestao/escritorio-virtual/anexos', [
            'vigente' => $vigente === null ? null : array_merge([
                'version' => $vigente->version,
                'published_at' => $vigente->published_at?->toDateString(),
                'publicado_por' => $vigente->publisher?->name,
                'fonte' => $vigente->source,
            ], $contagens($vigente)),
            'rascunho' => $rascunho === null ? null : array_merge([
                'version' => $rascunho->version,
                'autor' => [
                    'id' => $rascunho->created_by,
                    'name' => $rascunho->author?->name,
                ],
                'diff' => $this->service->diffRascunho($rascunho),
            ], $contagens($rascunho)),
            'historico' => $historico,
            'versaoSugerida' => 'ev-anexos-'.now()->format('Y-m-d'),
        ]);
    }

    public function store(PublicarAnexosEscritorioVirtualRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var UploadedFile $anexoA */
        $anexoA = $request->file('anexo_a');
        /** @var UploadedFile $anexoB */
        $anexoB = $request->file('anexo_b');

        $caminhoA = (string) $anexoA->store('temp', 'local');
        $caminhoB = (string) $anexoB->store('temp', 'local');

        try {
            $resultado = $this->service->importarParaRascunho(
                $validated['versao'],
                $validated['fonte'],
                Storage::disk('local')->path($caminhoA),
                Storage::disk('local')->path($caminhoB),
                (int) $request->user()->id,
            );
        } catch (RuntimeException|DomainException $e) {
            // CSV fora do cabeçalho esperado ou nome de versão já publicado —
            // erro de entrada do mantenedor, nunca 500.
            return back()->with('error', $e->getMessage());
        } finally {
            Storage::disk('local')->delete([$caminhoA, $caminhoB]);
        }

        return back()->with(
            'status',
            "Rascunho {$resultado['versao']->version} importado: {$resultado['anexo_a']} CNAEs no Anexo A e "
                ."{$resultado['anexo_b']} no Anexo B. A versão vigente não foi alterada — revise o diff e publique.",
        );
    }

    public function publicar(Request $request, string $versao): RedirectResponse
    {
        $rascunho = RuleVersion::versao(RuleDomain::AtividadesEscritorioVirtual, $versao)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->first();

        if ($rascunho === null) {
            return back()->with('error', 'Nenhum rascunho aberto com esta versão para publicação.');
        }

        try {
            $publicada = $this->service->publicar($rascunho, (int) $request->user()->id);
        } catch (FourEyesViolationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('gestao.escritorio-virtual.anexos.index')
            ->with('status', "Versão {$publicada->version} publicada — os Anexos A/B vigentes foram atualizados.");
    }
}
