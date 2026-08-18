<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreEmailServerRequest;
use App\Http\Requests\Gestao\UpdateEmailServerRequest;
use App\Models\EmailServer;
use App\Services\Email\EmailServerConnectionTester;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção dos servidores de e-mail administráveis (HU-014 / parametrização —
 * ConfigEmail). Segue o padrão do console: CRUD server-driven sob a permissão
 * manter-config-email. A senha é criptografada no model e NUNCA reexibida (a
 * listagem usa masked_password); na edição, senha em branco mantém a atual. A
 * auditoria (RN-002) é explícita e NUNCA inclui a senha (mesmo cuidado do
 * Parameter sensível). O servidor padrão ativo passa a controlar o envio via
 * MailConfigServiceProvider — a tela é uma feature real, não decorativa.
 */
class EmailServerController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(): Response
    {
        $servers = EmailServer::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (EmailServer $server): array => [
                'id' => $server->id,
                'name' => $server->name,
                'driver' => $server->driver,
                'host' => $server->host,
                'port' => $server->port,
                'encryption' => $server->encryption,
                'timeout' => $server->timeout,
                'username' => $server->username,
                'masked_password' => $server->masked_password,
                'from_address' => $server->from_address,
                'from_name' => $server->from_name,
                'active' => $server->active,
                'is_default' => $server->is_default,
            ]);

        return Inertia::render('gestao/config-email/index', [
            'servers' => $servers,
        ]);
    }

    public function store(StoreEmailServerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);
        unset($data['is_default']);

        $server = EmailServer::create($data);

        if ($isDefault) {
            $server->setAsDefault();
        }

        $this->audit->log('config-email', 'criar', "Servidor de e-mail '{$server->name}' cadastrado", $this->auditProps($server), $server);

        return back()->with('status', 'Servidor de e-mail cadastrado com sucesso.');
    }

    public function update(UpdateEmailServerRequest $request, EmailServer $emailServer): RedirectResponse
    {
        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);
        unset($data['is_default']);

        // Senha em branco = manter a atual (RN-009: nunca apaga a credencial por omissão).
        if (($data['password'] ?? '') === '') {
            unset($data['password']);
        }

        $emailServer->update($data);

        if ($isDefault) {
            $emailServer->setAsDefault();
        }

        $this->audit->log('config-email', 'atualizar', "Servidor de e-mail '{$emailServer->name}' atualizado", $this->auditProps($emailServer), $emailServer);

        return back()->with('status', 'Servidor de e-mail atualizado com sucesso.');
    }

    public function destroy(EmailServer $emailServer): RedirectResponse
    {
        $nome = $emailServer->name;
        $props = $this->auditProps($emailServer);

        $emailServer->delete();

        $this->audit->log('config-email', 'excluir', "Servidor de e-mail '{$nome}' excluído", $props);

        return back()->with('status', 'Servidor de e-mail excluído.');
    }

    public function test(Request $request, EmailServer $emailServer, EmailServerConnectionTester $tester): RedirectResponse
    {
        $validated = $request->validate(['recipient' => ['required', 'email']]);

        $result = $tester->test($emailServer, $validated['recipient']);

        $this->audit->log(
            'config-email',
            'testar',
            "Teste de conexão do servidor de e-mail '{$emailServer->name}'",
            $this->auditProps($emailServer) + ['recipient' => $validated['recipient']],
            $emailServer,
            result: $result['ok'] ? 'sucesso' : 'falha',
        );

        if (! $result['ok']) {
            return back()->withErrors(['recipient' => 'Falha no envio de teste: '.$result['message']]);
        }

        return back()->with('status', $result['message']);
    }

    /**
     * Propriedades auditáveis do servidor — NUNCA inclui a senha (RN-009).
     *
     * @return array<string, mixed>
     */
    private function auditProps(EmailServer $server): array
    {
        return [
            'email_server_id' => $server->id,
            'name' => $server->name,
            'driver' => $server->driver,
            'host' => $server->host,
            'port' => $server->port,
            'encryption' => $server->encryption,
            'from_address' => $server->from_address,
            'is_default' => $server->is_default,
            'active' => $server->active,
        ];
    }
}
