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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
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

    /** Sufixo do e-mail técnico de registros de balcão sem e-mail próprio. */
    public const SUFIXO_EMAIL_TECNICO = '@solicitante.viabiliza.local';

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

    /**
     * Registro criado pelo servidor (atendimento presencial, contingência ou
     * recebimento da JUCEB). Sem senha: a pessoa assume o registro ao se
     * cadastrar no portal com o mesmo CPF. CPF já cadastrado é reaproveitado
     * sem alterar os dados da pessoa.
     */
    public static function criarRegistroDeBalcao(string $cpf, string $nome, ?string $email, ?string $telefone): self
    {
        $existente = self::query()->where('cpf', $cpf)->first();

        if ($existente !== null) {
            return $existente;
        }

        $emailEmUso = $email !== null && self::query()->where('email', $email)->exists();

        $cidadao = new self([
            'name' => $nome,
            'cpf' => $cpf,
            'email' => ($email === null || $emailEmUso) ? $cpf.self::SUFIXO_EMAIL_TECNICO : $email,
            'phone' => $telefone,
        ]);

        try {
            // Savepoint: no PostgreSQL a violação de unicidade aborta a transação
            // corrente; aninhada, só o savepoint é desfeito e a releitura segue válida.
            DB::transaction(fn () => $cidadao->save());
        } catch (UniqueConstraintViolationException $e) {
            // Corrida: outra requisição criou o mesmo CPF (ou e-mail) entre a checagem e o insert.
            $vencedor = self::query()->where('cpf', $cpf)->first();

            if ($vencedor !== null) {
                return $vencedor;
            }

            // Foi o e-mail: refaz uma vez com o endereço técnico; se falhar de novo, relança.
            $cidadao->email = $cpf.self::SUFIXO_EMAIL_TECNICO;

            try {
                DB::transaction(fn () => $cidadao->save());
            } catch (UniqueConstraintViolationException) {
                return self::query()->where('cpf', $cpf)->first() ?? throw $e;
            }
        }

        return $cidadao;
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
