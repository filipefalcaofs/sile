<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\ProcessoPortalServicosResource;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestCpf;
use App\Services\PortalServicos\PortalServicos;
use App\Services\PortalServicos\PortalServicosIndisponivelException;
use App\Services\PortalServicos\PortalServicosSessaoClient;
use App\Services\PortalServicos\SessaoPortalServicosInvalidaException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Processos de viabilidade do CPF da sessão do cidadão no Portal de Serviços
 * (reunião de 23/10/2026). O CPF vem só do token conferido no BFF — nunca de
 * parâmetro —, e entram os processos de que ele é requerente, sócio do REGIN
 * ou solicitante declarado, sem rascunhos.
 */
class ProcessosPortalServicosController extends Controller
{
    private const LIMITE = 200;

    public function __invoke(Request $request, PortalServicosSessaoClient $cliente): JsonResponse
    {
        if (! PortalServicos::entradaDisponivel()) {
            return response()->json(['message' => 'Consulta indisponível.'], 503);
        }

        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Sessão do Portal de Serviços inválida.'], 401);
        }

        try {
            $cpf = $cliente->buscarSessao($token)->cpf;
        } catch (SessaoPortalServicosInvalidaException) {
            return response()->json(['message' => 'Sessão do Portal de Serviços inválida.'], 401);
        } catch (PortalServicosIndisponivelException) {
            return response()->json(['message' => 'Não foi possível confirmar a sessão agora.'], 503);
        }

        $contaId = User::query()->where('cpf', $cpf)->value('id');

        $processos = ViabilityRequest::query()
            ->where('status', '!=', ViabilityRequestStatus::Rascunho)
            ->where(fn ($query) => $query
                ->when($contaId !== null, fn ($dono) => $dono->where('requester_user_id', $contaId))
                ->orWhereHas('cpfsVinculados', fn ($vinculos) => $vinculos->where('cpf', $cpf)))
            ->with(['company', 'serviceType', 'decision', 'cpfsVinculados' => fn ($vinculos) => $vinculos->where('cpf', $cpf)])
            ->orderByDesc('protocoled_at')
            ->orderByDesc('id')
            ->limit(self::LIMITE)
            ->get();

        return response()->json([
            'data' => $processos->map(fn (ViabilityRequest $processo) => (new ProcessoPortalServicosResource(
                $processo,
                $this->vinculo($processo, $contaId),
            ))->resolve($request))->values(),
        ]);
    }

    private function vinculo(ViabilityRequest $processo, ?int $contaId): string
    {
        if ($contaId !== null && $processo->requester_user_id === $contaId) {
            return ViabilityRequestCpf::PAPEL_SOLICITANTE;
        }

        return $processo->cpfsVinculados->contains('papel', ViabilityRequestCpf::PAPEL_SOLICITANTE)
            ? ViabilityRequestCpf::PAPEL_SOLICITANTE
            : ViabilityRequestCpf::PAPEL_SOCIO;
    }
}
