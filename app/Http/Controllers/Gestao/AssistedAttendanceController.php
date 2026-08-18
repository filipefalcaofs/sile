<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\ViabilityRequestOrigin;
use App\Http\Controllers\Controller;
use App\Models\AssistedAttendance;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Rules\ValidCpf;
use App\Support\Audit\AuditService;
use App\Support\Representation\CurrentRepresentation;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Atendimento presencial assistido (HU-150): o atendente autorizado opera o
 * console "em nome de" o cidadão presente no balcão (inclusão digital),
 * REUSANDO o mecanismo de representação da Fase 1. O middleware
 * ResolveAssistedAttendance popula o MESMO CurrentRepresentation/Context, então
 * a abertura da solicitação grava requester = cidadão e created_by = atendente,
 * com a auditoria registrando os dois (RN-001/RN-002). O vínculo expira e exige
 * reabertura (CA-03); o escopo é limitado pela permissão atendimento-presencial.
 */
class AssistedAttendanceController extends Controller
{
    public function __construct(private AuditService $audit) {}

    /**
     * Estação de atendimento: iniciar (CPF), banner do atendimento ativo e
     * atalho para abrir a solicitação direta em nome do cidadão. Quando há
     * atendimento ativo (resolvido pelo middleware), traz as empresas do cidadão
     * e os tipos de serviço para a abertura.
     */
    public function index(): Response
    {
        $attendance = app(CurrentRepresentation::class)->attendance();
        $citizen = $attendance?->citizen;

        return Inertia::render('gestao/atendimento/index', [
            'attending' => $attendance === null ? null : [
                'citizen' => [
                    'id' => $citizen->id,
                    'name' => $citizen->name,
                    'cpf_masked' => $this->maskCpf($citizen->cpf),
                ],
                'expires_at' => $attendance->expires_at?->toIso8601String(),
            ],
            'companies' => $citizen === null ? [] : $this->citizenCompanies($citizen),
            'serviceTypes' => $attendance === null ? [] : ViabilityServiceType::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ]);
    }

    /**
     * Inicia o atendimento informando o CPF do cidadão presente (RN-001). O
     * cidadão precisa de conta no portal — não fabricamos cidadão (anti-fachada);
     * sem conta, bloqueia com orientação. O vínculo nasce com expiração curta
     * parametrizável (solicitacao.atendimento.expiracao_minutos).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            ['cpf' => ['required', 'string', new ValidCpf]],
            [],
            ['cpf' => 'CPF'],
        );

        $cpf = (string) preg_replace('/\D/', '', $validated['cpf']);
        $citizen = User::query()->where('cpf', $cpf)->first();

        if ($citizen === null) {
            throw ValidationException::withMessages([
                'cpf' => 'Não há cidadão cadastrado com este CPF. Oriente o cidadão a criar a conta para ser atendido.',
            ]);
        }

        if ($citizen->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'cpf' => 'Não é possível iniciar um atendimento para a sua própria conta.',
            ]);
        }

        $minutes = (int) Settings::get('solicitacao.atendimento.expiracao_minutos', 30);

        $attendance = AssistedAttendance::create([
            'attendant_user_id' => $request->user()->id,
            'citizen_user_id' => $citizen->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($minutes),
        ]);

        $request->session()->put('attending_attendance_id', $attendance->id);

        $this->audit->log('atendimento', 'atendimento-iniciado', 'Início de atendimento presencial assistido', [
            'attendance_id' => $attendance->id,
            'citizen_user_id' => $citizen->id,
        ]);

        return redirect()
            ->route('gestao.atendimento.index')
            ->with('status', "Atendimento iniciado em nome de {$citizen->name}.");
    }

    /**
     * Abre a solicitação direta (HU-061/renovação) EM NOME DO cidadão atendido,
     * reusando o domínio: requester = cidadão (usuário efetivo via
     * CurrentRepresentation), created_by = atendente, origem portal + vínculo de
     * atendimento (dimensão balcão, RN-005). Sem atendimento ativo (expirado),
     * exige reabertura (CA-03). A auditoria created registra os dois (RN-002).
     */
    public function storeSolicitacao(Request $request): RedirectResponse
    {
        $attendance = app(CurrentRepresentation::class)->attendance();

        if ($attendance === null) {
            return back()->with('error', 'O atendimento expirou ou não está ativo. Reabra o atendimento para continuar.');
        }

        if (! Settings::enabled('solicitacao_viabilidade')) {
            return back()->with('status', 'A criação de solicitações de viabilidade está temporariamente desativada pelo administrador.');
        }

        $citizen = $attendance->citizen;

        $validated = $request->validate([
            'service_type_id' => [
                'required', 'integer',
                Rule::exists('viability_service_types', 'id')->where('active', true),
            ],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ], [
            'service_type_id.required' => 'Selecione o tipo de serviço.',
            'service_type_id.exists' => 'O tipo de serviço selecionado é inválido ou está inativo.',
            'company_id.required' => 'Selecione a empresa da solicitação.',
            'company_id.exists' => 'A empresa selecionada é inválida.',
        ]);

        // Escopo do cidadão atendido: a empresa deve ter vínculo ATIVO dele —
        // o atendente não abre solicitação para empresa de terceiro.
        $hasActiveLink = Company::query()
            ->whereKey($validated['company_id'])
            ->whereHas('links', fn ($query) => $query
                ->where('user_id', $citizen->id)
                ->whereNull('ended_at'))
            ->exists();

        if (! $hasActiveLink) {
            throw ValidationException::withMessages([
                'company_id' => 'O cidadão atendido não possui vínculo ativo com esta empresa.',
            ]);
        }

        DB::transaction(fn () => ViabilityRequest::create([
            'origin' => ViabilityRequestOrigin::Portal,
            'service_type_id' => $validated['service_type_id'],
            'company_id' => $validated['company_id'],
            'requester_user_id' => $citizen->id,
            'created_by_user_id' => $request->user()->id,
            'assisted_attendance_id' => $attendance->id,
        ]));

        return redirect()
            ->route('gestao.atendimento.index')
            ->with('status', "Solicitação aberta em nome de {$citizen->name}.");
    }

    /**
     * Encerra o atendimento ativo (libera a estação). Registrado na auditoria.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $attendanceId = $request->session()->pull('attending_attendance_id');

        $attendance = $attendanceId === null
            ? null
            : AssistedAttendance::query()
                ->where('id', $attendanceId)
                ->where('attendant_user_id', $request->user()->id)
                ->first();

        if ($attendance !== null && $attendance->ended_at === null) {
            $attendance->forceFill(['ended_at' => now()])->save();
        }

        app(CurrentRepresentation::class)->clearAttendance();

        $this->audit->log('atendimento', 'atendimento-encerrado', 'Encerramento de atendimento presencial assistido', [
            'attendance_id' => $attendance?->id,
        ]);

        return redirect()
            ->route('gestao.atendimento.index')
            ->with('status', 'Atendimento encerrado.');
    }

    /**
     * Empresas com vínculo ATIVO do cidadão atendido — fonte do select da
     * abertura em nome de.
     *
     * @return array<int, array{id: int, legal_name: string, formatted_cnpj: string}>
     */
    private function citizenCompanies(User $citizen): array
    {
        return Company::query()
            ->whereHas('links', fn ($query) => $query
                ->where('user_id', $citizen->id)
                ->whereNull('ended_at'))
            ->orderBy('legal_name')
            ->get()
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'legal_name' => $company->legal_name,
                'formatted_cnpj' => $company->formatted_cnpj,
            ])
            ->all();
    }

    /**
     * Mascara o CPF para exibição (LGPD): •••.•••.•••-DD.
     */
    private function maskCpf(string $cpf): string
    {
        return '•••.•••.•••-'.substr($cpf, -2);
    }
}
