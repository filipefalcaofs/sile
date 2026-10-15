<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Factories\CidadaoFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Spatie\Activitylog\Models\Concerns\CausesActivity;

/**
 * Conta do cidadão no portal. Identidade independente do servidor da retaguarda
 * (`User`): tabela, guard `web`, provider e broker de senha próprios.
 */
#[Fillable(['name', 'email', 'cpf', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Cidadao extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<CidadaoFactory> */
    use CausesActivity, HasAuditoria, HasFactory, Notifiable;

    protected $table = 'cidadaos';

    /**
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

    public function isInactive(): bool
    {
        return $this->inactivated_at !== null;
    }

    /** Registro criado no balcão nasce sem senha e só vira conta quando a pessoa se cadastra. */
    public function temSenha(): bool
    {
        return $this->password !== null;
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

    public function govBrAccount(): HasOne
    {
        return $this->hasOne(GovBrAccount::class, 'cidadao_id');
    }

    public function hasAcceptedTerm(LegalTerm $term): bool
    {
        return $this->termAcceptances()->where('legal_term_id', $term->id)->exists();
    }
}
