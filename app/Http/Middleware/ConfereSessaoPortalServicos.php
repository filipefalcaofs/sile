<?php

namespace App\Http\Middleware;

use App\Services\PortalServicos\PortalServicos;
use App\Services\PortalServicos\PortalServicosIndisponivelException;
use App\Services\PortalServicos\PortalServicosSessaoClient;
use App\Services\PortalServicos\SessaoPortalServicosInvalidaException;
use App\Support\Settings;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * A sessão do portal do Viabiliza termina quando a do Portal de Serviços
 * termina (HU-142). Quem entrou pelo portal tem o token guardado na sessão;
 * a cada intervalo ele é conferido no BFF. Token recusado ou de outro CPF
 * derruba a sessão; BFF fora do ar não derruba e só tenta de novo depois de
 * um minuto.
 */
class ConfereSessaoPortalServicos
{
    private const ESPERA_APOS_FALHA = 60;

    public function __construct(private readonly PortalServicosSessaoClient $cliente) {}

    public function handle(Request $request, Closure $next): Response
    {
        $sessao = $request->session();
        $cifrado = $sessao->get('portal_servicos.token');

        if (! is_string($cifrado) || ! $this->venceuAConferencia($request)) {
            return $next($request);
        }

        try {
            $dados = $this->cliente->buscarSessao(Crypt::decryptString($cifrado));
        } catch (PortalServicosIndisponivelException) {
            $sessao->put('portal_servicos.tentado_em', now()->timestamp);

            return $next($request);
        } catch (SessaoPortalServicosInvalidaException|DecryptException) {
            return $this->encerrar($request);
        }

        if ($dados->cpf !== $request->user('web')?->cpf) {
            return $this->encerrar($request);
        }

        $sessao->put('portal_servicos.conferido_em', now()->timestamp);
        $sessao->forget('portal_servicos.tentado_em');

        return $next($request);
    }

    private function venceuAConferencia(Request $request): bool
    {
        $agora = now()->timestamp;
        $intervalo = (int) Settings::get('integrations.portal_servicos.intervalo_checagem_sessao', 300);
        $conferidoEm = (int) $request->session()->get('portal_servicos.conferido_em', 0);
        $tentadoEm = (int) $request->session()->get('portal_servicos.tentado_em', 0);

        return $agora - $conferidoEm >= $intervalo && $agora - $tentadoEm >= self::ESPERA_APOS_FALHA;
    }

    private function encerrar(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $destino = PortalServicos::urlLogin(PortalServicos::urlEntrada());

        return $request->header('X-Inertia')
            ? Inertia::location($destino)
            : redirect()->away($destino);
    }
}
