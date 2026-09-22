<?php

namespace App\Services\GovBr;

use App\Models\AccessLog;
use App\Models\GovBrAccount;
use App\Models\User;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Resolve o usuário local a partir da conta GOV.BR autenticada (HU-151).
 *
 * Identidade exclusivamente pelo CPF (`sub` do id_token — RN-003): CPF
 * encontrado vincula e autentica; CPF inexistente cria conta real de cidadão.
 * E-mail nunca vincula sozinho (CA-07) e conta inativada não autentica (CA-05).
 */
class GovBrAuthService
{
    private const LEVEL_RANKS = ['bronze' => 1, 'prata' => 2, 'ouro' => 3];

    private const LEVEL_FROM_GOVBR = ['bronze' => 'bronze', 'silver' => 'prata', 'gold' => 'ouro'];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @throws GovBrAuthException
     */
    public function authenticate(SocialiteUser $govBrUser): User
    {
        /** @var array<string, mixed> $claims */
        $claims = $govBrUser->getRaw();

        $cpf = preg_replace('/\D/', '', (string) ($claims['sub'] ?? ''));

        if (strlen($cpf) !== 11) {
            throw new GovBrAuthException('sub do id_token do GOV.BR não corresponde a um CPF.');
        }

        $level = $this->reliabilityLevel($claims);

        $this->ensureMinimumLevel($level, $cpf);

        $user = User::query()->where('cpf', $cpf)->first();

        if ($user !== null) {
            $this->ensureActive($user);
            $this->syncAccount($user, $claims, $level);

            return $user;
        }

        return $this->register($claims, $cpf, $level);
    }

    /**
     * Nível em pt-BR a partir do reliability_info do id_token; ausência é
     * tratada como bronze — o piso de qualquer conta GOV.BR (fail-closed
     * quando o mínimo parametrizado for maior).
     *
     * @param  array<string, mixed>  $claims
     */
    private function reliabilityLevel(array $claims): string
    {
        $govBrLevel = strtolower((string) data_get($claims, 'reliability_info.level', 'bronze'));

        return self::LEVEL_FROM_GOVBR[$govBrLevel] ?? 'bronze';
    }

    /**
     * @throws GovBrAuthException
     */
    private function ensureMinimumLevel(string $level, string $cpf): void
    {
        $minimum = strtolower((string) Settings::get('security.govbr.minimum_level', 'bronze'));

        $required = self::LEVEL_RANKS[$minimum] ?? self::LEVEL_RANKS['bronze'];

        if ((self::LEVEL_RANKS[$level] ?? 0) >= $required) {
            return;
        }

        $this->audit->logBlocked('acessos', 'Login GOV.BR bloqueado por nível de confiabilidade insuficiente', [
            'cpf' => $this->maskCpf($cpf),
            'nivel_conta' => $level,
            'nivel_minimo' => $minimum,
        ]);

        throw new GovBrAuthException(
            "Nível de confiabilidade {$level} abaixo do mínimo parametrizado ({$minimum}).",
            "Sua conta GOV.BR é nível {$level} e este serviço exige nível {$minimum} ou superior. Eleve o nível da sua conta em gov.br e tente novamente.",
        );
    }

    /**
     * @throws GovBrAuthException
     */
    private function ensureActive(User $user): void
    {
        if (! $user->isInactive()) {
            return;
        }

        // Paridade com o login local (Fortify::authenticateUsing).
        AccessLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'event' => 'inativada',
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'channel' => 'portal',
        ]);

        throw new GovBrAuthException(
            'Conta local inativada tentou autenticar via GOV.BR.',
            __('Sua conta está inativa. Procure o administrador do sistema.'),
        );
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function syncAccount(User $user, array $claims, string $level): void
    {
        $account = GovBrAccount::query()->firstOrNew(['user_id' => $user->id]);

        if (! $account->exists) {
            $account->linked_at = now();
        }

        $account->fill([
            'reliability_level' => $level,
            'reliability_levels' => $this->reliabilityIds($claims),
            'last_authenticated_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return list<string>
     */
    private function reliabilityIds(array $claims): array
    {
        $reliabilities = data_get($claims, 'reliability_info.reliabilities', []);

        return collect(is_array($reliabilities) ? $reliabilities : [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $claims
     *
     * @throws GovBrAuthException
     */
    private function register(array $claims, string $cpf, string $level): User
    {
        $email = isset($claims['email']) ? strtolower(trim((string) $claims['email'])) : null;
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($email === null || $email === '' || ! $emailVerified) {
            throw new GovBrAuthException(
                'Primeiro acesso via GOV.BR sem e-mail verificado no provedor.',
                'Sua conta GOV.BR não possui e-mail verificado. Verifique seu e-mail no gov.br ou crie uma conta com e-mail e senha.',
            );
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new GovBrAuthException(
                'E-mail do GOV.BR já pertence a usuário local com outro CPF.',
                'O e-mail da sua conta GOV.BR já está em uso por outra conta neste portal. Entre com e-mail e senha ou procure o suporte.',
            );
        }

        $name = trim((string) ($claims['name'] ?? ''));

        if ($name === '') {
            throw new GovBrAuthException('id_token do GOV.BR sem o claim name.');
        }

        return DB::transaction(function () use ($claims, $cpf, $level, $email, $name): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'cpf' => $cpf,
                'password' => Str::password(40),
            ]);

            // E-mail já verificado pelo GOV.BR (RN-007) — dispensa reverificação local.
            $user->markEmailAsVerified();

            $user->assignRole('cidadao');

            GovBrAccount::create([
                'user_id' => $user->id,
                'reliability_level' => $level,
                'reliability_levels' => $this->reliabilityIds($claims),
                'linked_at' => now(),
                'last_authenticated_at' => now(),
            ]);

            $this->audit->log(
                'usuarios',
                'cadastro-govbr',
                'Conta criada no primeiro acesso via GOV.BR',
                [
                    'origem' => 'govbr',
                    'cpf' => $this->maskCpf($cpf),
                    'nivel_confiabilidade' => $level,
                ],
                $user,
            );

            return $user;
        });
    }

    private function maskCpf(string $cpf): string
    {
        return '***.***.***-'.substr($cpf, -2);
    }
}
