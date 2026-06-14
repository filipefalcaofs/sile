<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        ];
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

    public function isInactive(): bool
    {
        return $this->inactivated_at !== null;
    }

    public function termAcceptances(): HasMany
    {
        return $this->hasMany(LegalTermAcceptance::class);
    }

    public function govBrAccount(): HasOne
    {
        return $this->hasOne(GovBrAccount::class);
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
