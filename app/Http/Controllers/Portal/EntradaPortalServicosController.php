<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\PortalServicos\EntradaPortalServicosRecusadaException;
use App\Services\PortalServicos\PortalServicos;
use App\Services\PortalServicos\PortalServicosAuthService;
use App\Services\PortalServicos\PortalServicosIndisponivelException;
use App\Services\PortalServicos\PortalServicosSessaoClient;
use App\Services\PortalServicos\SessaoPortalServicosInvalidaException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entrada do cidadão pelo Portal de Serviços da SEDUR (HU-142). O serviço da
 * Carta de Serviços abre esta rota com o token da sessão do portal; o token é
 * conferido no BFF, a conta é achada ou criada pelo CPF e a sessão do portal
 * do Viabiliza é aberta. O token só fica na sessão, cifrado, para a conferência
 * periódica — nunca no endereço final.
 */
class EntradaPortalServicosController extends Controller
{
    /** @var array<string, array{0: string, 1: array<string, string>}> */
    public const DESTINOS = [
        'renovacao' => ['portal.renovacao.index', []],
        'consulta' => ['portal.solicitacoes.index', []],
        'convites' => ['portal.solicitacoes.index', ['status' => 'em_pendencia']],
    ];

    public function __invoke(
        Request $request,
        PortalServicosSessaoClient $cliente,
        PortalServicosAuthService $auth,
    ): RedirectResponse|Response {
        if (! PortalServicos::entradaDisponivel()) {
            return redirect()->route('login')->with('status', 'Entrada pelo Portal de Serviços indisponível no momento.');
        }

        $destino = $request->query('destino');
        $destino = is_string($destino) && isset(self::DESTINOS[$destino]) ? $destino : null;
        $token = $request->query('token');

        if (! is_string($token) || $token === '' || $token === PortalServicos::MARCADOR_TOKEN) {
            return redirect()->away(PortalServicos::urlLogin(PortalServicos::urlEntrada($destino)));
        }

        try {
            $user = $auth->autenticar($cliente->buscarSessao($token));
        } catch (SessaoPortalServicosInvalidaException) {
            return $this->aviso('Sua entrada pelo Portal de Serviços expirou. Entre de novo pelo Portal de Serviços da SEDUR.');
        } catch (PortalServicosIndisponivelException) {
            return $this->aviso('Não foi possível confirmar sua entrada agora. Tente de novo em alguns minutos.');
        } catch (EntradaPortalServicosRecusadaException $exception) {
            return $this->aviso($exception->mensagemUsuario);
        }

        $intended = $this->intendedDoPortal($request);

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
        }

        $request->attributes->set('auth_origin', 'servicos');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('portal_servicos.token', Crypt::encryptString($token));
        $request->session()->put('portal_servicos.conferido_em', now()->timestamp);

        return redirect()->to($intended ?? $this->urlDestino($destino));
    }

    private function aviso(string $mensagem): Response
    {
        return Inertia::render('auth/entrada-portal-servicos', [
            'mensagem' => $mensagem,
            'cartaUrl' => PortalServicos::urlCarta(),
        ]);
    }

    /**
     * Página protegida que o cidadão tentou abrir antes de entrar. Só vale se
     * for do próprio portal; qualquer outra coisa (console, outro host) cai.
     */
    private function intendedDoPortal(Request $request): ?string
    {
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended)) {
            return null;
        }

        $host = parse_url($intended, PHP_URL_HOST);
        $path = (string) parse_url($intended, PHP_URL_PATH);

        if ($host !== $request->getHost() || ! str_starts_with($path, '/portal/') || str_starts_with($path, '/portal/entrar/')) {
            return null;
        }

        return $intended;
    }

    private function urlDestino(?string $destino): string
    {
        if ($destino === null) {
            return route('portal.dashboard');
        }

        [$rota, $parametros] = self::DESTINOS[$destino];

        return route($rota, $parametros);
    }
}
