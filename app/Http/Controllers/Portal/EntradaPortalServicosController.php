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
    /**
     * Serviços da Carta de Serviços e o Detalhar da área logada do Portal de
     * Serviços (reunião de 23/10/2026). `processo` abre o processo pelo
     * número do processo, da viabilidade ou do BAP; o autônomo ainda não tem
     * fluxo próprio e cai na nova solicitação.
     *
     * @var array<string, string|null>
     */
    public const DESTINOS = [
        'renovacao' => 'portal.renovacao.index',
        'nova' => 'portal.dashboard',
        'autonomo' => 'portal.dashboard',
        'processo' => null,
    ];

    private const PEDIDO_NA_JUCEB = 'Os pedidos de viabilidade começam na JUCEB. Pelo portal você renova viabilidade, consulta processo e responde convite.';

    public function __invoke(
        Request $request,
        PortalServicosSessaoClient $cliente,
        PortalServicosAuthService $auth,
    ): RedirectResponse|Response {
        if (! PortalServicos::entradaDisponivel()) {
            return redirect()->route('login')->with('status', 'Entrada pelo Portal de Serviços indisponível no momento.');
        }

        $destino = $request->query('destino');
        $destino = is_string($destino) && array_key_exists($destino, self::DESTINOS) ? $destino : null;
        $numero = $this->numero($request);
        $token = $request->query('token');

        if (! is_string($token) || $token === '' || $token === PortalServicos::MARCADOR_TOKEN) {
            return redirect()->away(PortalServicos::urlLogin(PortalServicos::urlEntrada($destino, $numero)));
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

        // Detalhar da área logada do Portal: a consulta por número serve a
        // qualquer cidadão (requerente, sócio ou quem tem o número).
        if ($destino === 'processo') {
            return redirect()->route('portal.consulta', array_filter(['numero' => $numero]));
        }

        // Pedido novo começa na JUCEB: o portal só orienta.
        if ($destino === 'nova' || $destino === 'autonomo') {
            return redirect()->route('portal.dashboard')->with('status', self::PEDIDO_NA_JUCEB);
        }

        // Destino explícito (serviço da Carta) vence a página guardada antes do login.
        return redirect()->to($destino !== null ? route(self::DESTINOS[$destino]) : ($intended ?? route('portal.dashboard')));
    }

    private function numero(Request $request): ?string
    {
        $numero = $request->query('numero');

        if (! is_string($numero)) {
            return null;
        }

        $numero = strtoupper(trim($numero));

        return preg_match('#^[A-Z0-9][A-Z0-9./-]{0,59}$#', $numero) === 1 ? $numero : null;
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
}
