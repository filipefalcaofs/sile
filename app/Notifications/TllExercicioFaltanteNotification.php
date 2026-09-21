<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso a gestores: a tabela TLL de um ou mais exercícios ainda não foi
 * publicada. Só informa — nunca grava valor nem publica o rascunho.
 */
class TllExercicioFaltanteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $exercicios
     */
    public function __construct(public readonly array $exercicios) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $anos = implode(', ', $this->exercicios);

        return (new MailMessage)
            ->subject('Tabela TLL sem publicação — exercício '.$anos)
            ->greeting('Olá!')
            ->line("A tabela de valores TLL ainda não foi publicada para o(s) exercício(s) {$anos}.")
            ->line('Gere o rascunho pelo fator do decreto e peça a um segundo usuário para publicar.')
            ->action('Abrir valores TLL', url('/gestao/tll'))
            ->line('Este é um aviso automático — não responda a esta mensagem.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => 'tll-exercicio-faltante',
            'titulo' => 'Tabela TLL sem publicação',
            'exercicios' => $this->exercicios,
            'url' => '/gestao/tll',
        ];
    }
}
