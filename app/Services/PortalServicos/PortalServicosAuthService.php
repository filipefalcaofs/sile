<?php

namespace App\Services\PortalServicos;

use App\Enums\AuthType;
use App\Models\AccessLog;
use App\Models\User;
use App\Rules\ValidCpf;
use App\Services\Sedur\SedurContatoClient;
use App\Support\Audit\AuditService;
use App\Support\EmailProvisorio;
use App\Support\PerfilCidadao;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Conta do Viabiliza a partir da sessão do Portal de Serviços (HU-140/142),
 * no mesmo modelo do AD no console: o portal confirma quem é a pessoa, a conta
 * fica aqui. Como o portal passa a ser o único caminho de entrada do cidadão,
 * e-mail ausente, em uso ou pendente nunca trava a entrada: a conta usa o
 * e-mail provisório e nasce (ou fica) confirmada.
 */
class PortalServicosAuthService
{
    private const NIVEIS = ['bronze' => 1, 'prata' => 2, 'ouro' => 3];

    public function __construct(
        private readonly AuditService $audit,
        private readonly SedurContatoClient $contato,
    ) {}

    /**
     * @throws EntradaPortalServicosRecusadaException
     */
    public function autenticar(PortalServicosSessao $sessao): User
    {
        $this->garantirCpfValido($sessao->cpf);
        $this->garantirNivelMinimo($sessao);

        $user = User::query()->where('cpf', $sessao->cpf)->first();

        if ($user !== null) {
            $this->garantirAtiva($user);
            $this->regularizarEmail($user, $sessao);

            return $user;
        }

        return $this->cadastrar($sessao);
    }

    private function garantirCpfValido(string $cpf): void
    {
        if (Validator::make(['cpf' => $cpf], ['cpf' => [new ValidCpf]])->passes()) {
            return;
        }

        throw new EntradaPortalServicosRecusadaException(
            'CPF da sessão do Portal de Serviços é inválido.',
            'Não foi possível confirmar seu CPF. Entre de novo pelo Portal de Serviços da SEDUR.',
        );
    }

    /**
     * Só vale quando a sessão traz o nível: sem ele, o portal já exigiu o
     * GOV.BR e não há o que comparar.
     */
    private function garantirNivelMinimo(PortalServicosSessao $sessao): void
    {
        if ($sessao->nivel === null) {
            return;
        }

        $minimo = strtolower((string) Settings::get('security.govbr.minimum_level', 'bronze'));

        if ((self::NIVEIS[$sessao->nivel] ?? 0) >= (self::NIVEIS[$minimo] ?? 1)) {
            return;
        }

        $this->audit->logBlocked('acessos', 'Entrada pelo Portal de Serviços bloqueada por nível GOV.BR insuficiente', [
            'cpf' => $this->mascararCpf($sessao->cpf),
            'nivel_conta' => $sessao->nivel,
            'nivel_minimo' => $minimo,
        ]);

        throw new EntradaPortalServicosRecusadaException(
            "Nível {$sessao->nivel} abaixo do mínimo {$minimo}.",
            "Sua conta GOV.BR é nível {$sessao->nivel} e este serviço exige nível {$minimo} ou superior. Eleve o nível da sua conta em gov.br e tente novamente.",
        );
    }

    private function garantirAtiva(User $user): void
    {
        if (! $user->isInactive()) {
            return;
        }

        AccessLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'event' => 'inativada',
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'channel' => 'portal',
        ]);

        throw new EntradaPortalServicosRecusadaException(
            'Conta inativada tentou entrar pelo Portal de Serviços.',
            'Sua conta está inativa. Procure a SEDUR.',
        );
    }

    /**
     * Conta vinda do REGIN (e-mail pendente ou provisório): troca o provisório
     * pelo e-mail da sessão quando ele está livre e confirma em qualquer caso.
     * O tipo da conta (local, ad) nunca muda aqui.
     */
    private function regularizarEmail(User $user, PortalServicosSessao $sessao): void
    {
        $provisorio = EmailProvisorio::eh($user->email);

        if ($user->hasVerifiedEmail() && ! $provisorio) {
            return;
        }

        if ($provisorio && $this->emailLivre($sessao->email, $user)) {
            $user->forceFill(['email' => $sessao->email])->save();
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $this->audit->log(
            'usuarios',
            'email-confirmado-portal-servicos',
            'E-mail confirmado na entrada pelo Portal de Serviços',
            ['cpf' => $this->mascararCpf($user->cpf), 'email_provisorio' => EmailProvisorio::eh($user->email)],
            $user,
        );
    }

    private function cadastrar(PortalServicosSessao $sessao): User
    {
        $email = $this->emailLivre($sessao->email) ? $sessao->email : EmailProvisorio::para($sessao->cpf);

        try {
            // auth_type fica fora do Fillable do User: só código confiável o define.
            $user = new User;
            $user->forceFill([
                'name' => $sessao->nome ?? $this->nomeDoCadastro($sessao->cpf),
                'email' => $email,
                'cpf' => $sessao->cpf,
                'password' => Hash::make(Str::random(40)),
                'auth_type' => AuthType::PortalServicos,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            // Outra aba criou a conta entre a busca e o insert.
            $existente = User::query()->where('cpf', $sessao->cpf)->first();

            if ($existente !== null) {
                return $existente;
            }

            throw new EntradaPortalServicosRecusadaException(
                'Conflito de unicidade ao criar a conta pelo Portal de Serviços.',
                'Não foi possível concluir sua entrada agora. Tente de novo pelo Portal de Serviços da SEDUR.',
            );
        }

        DB::transaction(function () use ($user): void {
            $user->markEmailAsVerified();
            $user->assignRole(PerfilCidadao::nome());

            $this->audit->log(
                'usuarios',
                'cadastro-portal-servicos',
                'Conta criada no primeiro acesso pelo Portal de Serviços',
                [
                    'origem' => 'portal_servicos',
                    'cpf' => $this->mascararCpf($user->cpf),
                    'email_provisorio' => EmailProvisorio::eh($user->email),
                ],
                $user,
            );
        });

        return $user;
    }

    private function emailLivre(?string $email, ?User $dono = null): bool
    {
        if ($email === null) {
            return false;
        }

        return ! User::query()
            ->where('email', $email)
            ->when($dono !== null, fn ($query) => $query->whereKeyNot($dono->id))
            ->exists();
    }

    private function nomeDoCadastro(string $cpf): string
    {
        try {
            $nome = trim($this->contato->consultarCpf($cpf)->nome);
        } catch (Throwable) {
            $nome = '';
        }

        return $nome !== ''
            ? $nome
            : substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2);
    }

    private function mascararCpf(string $cpf): string
    {
        return '***.***.***-'.substr($cpf, -2);
    }
}
