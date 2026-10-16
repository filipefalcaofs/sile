<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\AuthType;
use App\Notifications\GestaoResetPasswordQueued;
use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'cpf', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use CausesActivity, HasAuditoria, HasFactory, HasRoles, Notifiable;

    /**
     * Papéis e permissões (spatie) são um conceito único da aplicação,
     * sempre no guard web — independente do guard de sessão usado para
     * autenticar (web no portal, gestao no console).
     */
    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'inactivated_at' => 'datetime',
            'password' => 'hashed',
            'auth_type' => AuthType::class,
        ];
    }

    /** Conta técnica da integração JUCEB/REGIN: autora (created_by) dos processos recebidos. Sem e-mail entregável. */
    public const EMAIL_INTEGRACAO_REGIN = 'solicitante-nao-identificado@regin.invalid';

    /**
     * Contas de sistema (integração REGIN, e-mail `.invalid` e atores ocultos do config):
     * não são pessoas, ficam fora da gestão de usuários e nunca viram cidadãs na separação.
     * Regra única, aplicada também à consulta de `users` sem model (migrations).
     *
     * @template TBuilder of Builder<User>|QueryBuilder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function excluirContasDeSistema(Builder|QueryBuilder $query, string $coluna = 'email'): Builder|QueryBuilder
    {
        $emails = array_values(array_unique(array_map('mb_strtolower', [
            self::EMAIL_INTEGRACAO_REGIN,
            ...(array) config('viabiliza.tramitacao.atores_ocultos.emails', []),
        ])));

        return $query
            ->whereNotIn(DB::raw("lower({$coluna})"), $emails)
            ->whereRaw("lower({$coluna}) not like ?", ['%.invalid']);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeSemContasDeSistema(Builder $query): Builder
    {
        return self::excluirContasDeSistema($query, $query->qualifyColumn('email'));
    }

    public function isContaDeSistema(): bool
    {
        return ! static::query()->whereKey($this->getKey())->semContasDeSistema()->exists();
    }

    public function temEmailEntregavel(): bool
    {
        $email = mb_strtolower((string) $this->email);

        return $email !== '' && ! str_ends_with($email, '.invalid');
    }

    public function isAdAccount(): bool
    {
        return $this->auth_type === AuthType::Ad;
    }

    /**
     * Conta de desenvolvedor/suporte, omitida como autor na tramitação.
     */
    public function isAtorOcultoNaTramitacao(): bool
    {
        $emails = array_map('mb_strtolower', (array) config('viabiliza.tramitacao.atores_ocultos.emails'));
        $usernames = array_map('mb_strtolower', (array) config('viabiliza.tramitacao.atores_ocultos.ad_usernames'));

        return in_array(mb_strtolower((string) $this->email), $emails, true)
            || ($this->ad_username !== null && in_array(mb_strtolower($this->ad_username), $usernames, true));
    }

    /**
     * Servidor local sem `acessar-gestao` (nem por perfil nem direta): continua listado na gestão,
     * mas não entra no console até receber um perfil com acesso. Conta do AD não conta.
     */
    public function semAcessoAGestao(): bool
    {
        return ! $this->isAdAccount() && ! $this->getAllPermissions()->contains('name', 'acessar-gestao');
    }

    public function sendEmailVerificationNotification(): void
    {
        $log = EmailLog::create([
            'recipient_email' => $this->email,
            'recipient_name' => $this->name,
            'notification_class' => VerifyEmailQueued::class,
            'status' => 'na_fila',
            'queued_at' => now(),
        ]);

        $notification = new VerifyEmailQueued;
        $notification->emailLogId = $log->id;
        $notification->freezeUrlFor($this);
        $this->notify($notification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $log = EmailLog::create([
            'recipient_email' => $this->email,
            'recipient_name' => $this->name,
            'notification_class' => ResetPasswordQueued::class,
            'status' => 'na_fila',
            'queued_at' => now(),
        ]);

        $notification = new ResetPasswordQueued($token);
        $notification->emailLogId = $log->id;
        $notification->freezeUrlFor($this);
        $this->notify($notification);
    }

    /**
     * Recuperação de senha do console (guard gestao): notification própria
     * com link para /gestao/reset-password, isolada do fluxo do portal.
     */
    public function sendGestaoPasswordResetNotification(string $token): void
    {
        $log = EmailLog::create([
            'recipient_email' => $this->email,
            'recipient_name' => $this->name,
            'notification_class' => GestaoResetPasswordQueued::class,
            'status' => 'na_fila',
            'queued_at' => now(),
        ]);

        $notification = new GestaoResetPasswordQueued($token);
        $notification->emailLogId = $log->id;
        $notification->freezeUrlFor($this);
        $this->notify($notification);
    }

    public function isInactive(): bool
    {
        return $this->inactivated_at !== null;
    }

    /**
     * Destino do canal WhatsApp (HU-095): o telefone do usuário em E.164. É o
     * "para onde" lido pelo WhatsAppChannel; sem telefone, o canal degrada honesto
     * (não envia). O provedor real entra na Fase 13 (troca só o binding do gateway).
     */
    public function routeNotificationForWhatsapp(?Notification $notification = null): ?string
    {
        return $this->phone;
    }

    /**
     * @return MorphMany<LegalTermAcceptance, $this>
     */
    public function termAcceptances(): MorphMany
    {
        return $this->morphMany(LegalTermAcceptance::class, 'accepted_by');
    }

    public function hasAcceptedTerm(LegalTerm $term): bool
    {
        return $this->termAcceptances()->where('legal_term_id', $term->id)->exists();
    }

    /**
     * Setores (caixas de análise) aos quais o analista está vinculado
     * (HU-138 RN-005 — vínculo N:N administrável pelo gestor).
     *
     * @return BelongsToMany<Sector, $this>
     */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'sector_user')->withTimestamps();
    }
}
