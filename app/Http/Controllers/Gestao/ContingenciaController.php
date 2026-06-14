<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\ViabilityRequestOrigin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\RegistrarContingenciaRequest;
use App\Models\DocumentRequirement;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Solicitacao\DocumentacaoIncompletaException;
use App\Services\Solicitacao\DuplicateRequestDetector;
use App\Services\Solicitacao\PropertyGeometryWriter;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Services\Solicitacao\SolicitacaoIncompletaException;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Canal de operador — registro de solicitação em CONTINGÊNCIA na retaguarda
 * (HU-148). O operador autorizado preenche o MESMO conjunto de dados do
 * formulário oficial e o sistema PROTOCOLA pelo MESMO motor do canal normal
 * (ProtocolarSolicitacaoService, 08-10) — nunca um atalho decisório (RN-002):
 * muda só a origem (`contingencia`, auditada — RN-003) e o ator (operador, em
 * created_by; o beneficiário é o requester).
 *
 * Contingência é o caminho REAL de operação enquanto o contrato Regin não chega
 * (Fase 13): zero adaptador simulado. A referência externa (BAP/Regin informado
 * pelo requerente) é gravada para vinculação posterior (HU-133) e não pode
 * duplicar processo (RN-004 — reusa o DuplicateRequestDetector). Reincidência
 * por CNPJ vira ALERTA não-bloqueante (direito de petição, como no portal).
 */
class ContingenciaController extends Controller
{
    public function __construct(
        private ProtocolarSolicitacaoService $protocolar,
        private DuplicateRequestDetector $duplicateDetector,
        private PropertyGeometryWriter $geometryWriter,
        private AuditService $audit,
    ) {}

    /**
     * Tela do console: o mesmo conjunto de dados do formulário oficial, com o
     * motivo da contingência e a referência externa. O processo nasce com origem
     * "contingência" (aviso visível na tela).
     */
    public function create(): Response
    {
        return Inertia::render('gestao/contingencia/create', [
            'serviceTypes' => ViabilityServiceType::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'flow_hint']),
            'mapa' => [
                'centro' => ['lat' => -12.9714, 'lng' => -38.5014],
                'zoom' => 13,
            ],
        ]);
    }

    /**
     * Registra em contingência e PROTOCOLA pelo mesmo serviço/motor do canal
     * normal, em UMA transação. Bloqueios do motor (dados mínimos, documentos
     * obrigatórios) revertem tudo (anti-fachada — nada meio-criado, número não
     * é consumido) e são comunicados; nunca há atalho nem falha silenciosa.
     */
    public function store(RegistrarContingenciaRequest $request): RedirectResponse
    {
        /** @var User $operator */
        $operator = $request->user();
        $beneficiary = $request->beneficiaryUser();
        $company = $request->beneficiaryCompany();
        $externalReference = $this->externalReference($request);

        // RN-004: a mesma referência externa (BAP/Regin) não pode gerar processo
        // duplicado — vínculo posterior aponta para o processo já existente.
        if ($externalReference !== null) {
            $duplicate = $this->duplicateDetector->detectByExternalReference($externalReference);

            if ($duplicate !== null) {
                return back()->withInput()->with(
                    'error',
                    "Já existe um processo registrado com a referência externa \"{$externalReference}\""
                    .($duplicate['protocol_number'] !== null ? " (protocolo {$duplicate['protocol_number']})" : '')
                    .'. A vinculação posterior não duplica o processo.',
                );
            }
        }

        // RN-007 (reuso do 08-05): reincidência por CNPJ é ALERTA, nunca bloqueio.
        $alert = $this->duplicateDetector->detect($company);

        /** @var array<int, array{disk: string, path: string}> $storedPaths */
        $storedPaths = [];

        try {
            $solicitacao = DB::transaction(function () use ($request, $operator, $beneficiary, $company, $externalReference, &$storedPaths): ViabilityRequest {
                $solicitacao = ViabilityRequest::create([
                    'origin' => ViabilityRequestOrigin::Contingencia,
                    'service_type_id' => $request->validated('service_type_id'),
                    'company_id' => $company->id,
                    'requester_user_id' => $beneficiary->id,
                    'created_by_user_id' => $operator->id,
                    'contingency_reason' => $request->validated('contingency_reason'),
                    'external_reference' => $externalReference,
                    'used_area_m2' => $request->validated('used_area_m2'),
                    'address_street' => $request->validated('address_street'),
                    'address_number' => $request->validated('address_number'),
                    'address_complement' => $request->validated('address_complement'),
                    'address_neighborhood' => $request->validated('address_neighborhood'),
                    'address_zip' => $request->validated('address_zip'),
                    'address_reference' => $request->validated('address_reference'),
                    'property_polygon_geojson' => $request->validated('property_polygon_geojson'),
                    'is_virtual_office' => $request->boolean('is_virtual_office'),
                    'is_public_area' => $request->boolean('is_public_area'),
                    'has_independent_access' => $request->boolean('has_independent_access'),
                ]);

                // status nasce do default do banco ('rascunho'); recarrega para
                // que o motor de protocolo veja o estado correto (a transição
                // rascunho→protocolada é a do canal normal — RN-002).
                $solicitacao->refresh();

                $this->syncCnaes($solicitacao, $request);
                $storedPaths = $this->attachDocuments($solicitacao, $request, $operator);

                // Geometria derivada (pgsql) a partir do GeoJSON — mesmo writer do 08-06.
                $this->geometryWriter->write($solicitacao);

                // MESMO motor do canal normal — sem atalho (RN-002).
                return $this->protocolar->protocol($solicitacao, $operator);
            });
        } catch (DocumentacaoIncompletaException|SolicitacaoIncompletaException $e) {
            $this->discardStoredFiles($storedPaths);

            return back()->withInput()->with('error', $e->getMessage());
        }

        $redirect = redirect()
            ->route('gestao.contingencia.create')
            ->with('status', "Solicitação registrada em contingência e protocolada sob o número {$solicitacao->protocol_number}.");

        if ($alert !== null) {
            $redirect->with('duplicateAlert', $alert);
        }

        return $redirect;
    }

    /**
     * Sincroniza o pivot de atividades: exatamente um principal (is_primary) +
     * complementares (espelha o sync único do 08-07).
     */
    private function syncCnaes(ViabilityRequest $solicitacao, RegistrarContingenciaRequest $request): void
    {
        $payload = [(int) $request->validated('principal_cnae_id') => ['is_primary' => true]];

        foreach ((array) $request->validated('complementares', []) as $cnaeId) {
            $payload[(int) $cnaeId] = ['is_primary' => false];
        }

        $solicitacao->cnaes()->sync($payload);
    }

    /**
     * Grava os anexos enviados (mapeados por requirement_id), espelhando o
     * armazenamento do 08-08 (disk parametrizado nunca público, sha256 REAL,
     * auditoria RN-002). Retorna os caminhos gravados para limpeza em caso de
     * rollback do protocolo.
     *
     * @return array<int, array{disk: string, path: string}>
     */
    private function attachDocuments(ViabilityRequest $solicitacao, RegistrarContingenciaRequest $request, User $operator): array
    {
        $disk = $this->documentsDisk();
        $stored = [];

        /** @var array<int|string, UploadedFile> $files */
        $files = $request->file('documents', []);

        foreach ($files as $requirementKey => $file) {
            $requirementId = (int) $requirementKey;

            // Só anexa para um requisito que existe (defesa de FK; chave inválida ignorada).
            if (! DocumentRequirement::query()->whereKey($requirementId)->exists()) {
                continue;
            }

            // Metadados antes do store() (a movimentação invalida getRealPath).
            $sha256 = hash_file('sha256', $file->getRealPath());
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            $path = $file->store('solicitacoes/'.$solicitacao->id, $disk);
            $stored[] = ['disk' => $disk, 'path' => $path];

            $document = $solicitacao->documents()->create([
                'requirement_id' => $requirementId,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size' => $size,
                'sha256' => $sha256,
                'uploaded_by_user_id' => $operator->id,
            ]);

            $this->audit->log('solicitacoes', 'documento-anexado', 'Documento anexado em contingência', [
                'solicitacao_id' => $solicitacao->id,
                'documento_id' => $document->id,
                'requirement_id' => $requirementId,
                'origem' => ViabilityRequestOrigin::Contingencia->value,
                'sha256' => $sha256,
            ], $solicitacao);
        }

        return $stored;
    }

    /**
     * Remove do disco os anexos gravados quando o protocolo é revertido (o
     * arquivo físico não participa do rollback transacional).
     *
     * @param  array<int, array{disk: string, path: string}>  $storedPaths
     */
    private function discardStoredFiles(array $storedPaths): void
    {
        foreach ($storedPaths as $stored) {
            Storage::disk($stored['disk'])->delete($stored['path']);
        }
    }

    private function externalReference(RegistrarContingenciaRequest $request): ?string
    {
        $reference = trim((string) $request->validated('external_reference'));

        return $reference === '' ? null : $reference;
    }

    /**
     * Disk dos documentos (HU-014): banco→cache→config, default 'local'. NUNCA
     * público — o acesso é sempre por streaming autenticado (LGPD).
     */
    private function documentsDisk(): string
    {
        return (string) Settings::get(
            'storage.documentos.disk',
            config('sile.storage.documentos.disk', 'local'),
        );
    }
}
