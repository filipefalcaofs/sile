<?php

namespace App\Notifications\Messages;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * E-mail no modelo visual do Viabiliza (emails/notificacao): selo de contexto,
 * título, quadro de dados e destaque, além das linhas e do botão do MailMessage.
 * As linhas continuam em introLines/outroLines — só a apresentação muda.
 */
class EmailViabiliza extends MailMessage
{
    public function __construct()
    {
        $this->markdown('emails.notificacao');
    }

    /**
     * @param  'info'|'alerta'|'sucesso'|'erro'|'neutro'  $tipo
     */
    public function selo(string $texto, string $tipo = 'info'): static
    {
        $this->viewData['selo'] = [$texto, $tipo];

        return $this;
    }

    public function titulo(string $titulo): static
    {
        $this->viewData['titulo'] = $titulo;

        return $this;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2?: string}>  $itens  [rótulo, valor, tom opcional (erro|sucesso)]
     */
    public function dados(array $itens): static
    {
        $this->viewData['dados'] = $itens;

        return $this;
    }

    /**
     * @param  'info'|'alerta'  $tipo
     */
    public function destaque(string $texto, string $tipo = 'info'): static
    {
        $this->viewData['destaque'] = [$texto, $tipo];

        return $this;
    }
}
