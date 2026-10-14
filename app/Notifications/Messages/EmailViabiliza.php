<?php

namespace App\Notifications\Messages;

use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

/**
 * E-mail no modelo visual do Viabiliza (emails/notificacao): selo de contexto,
 * título, quadro de dados e destaque, além das linhas e do botão do MailMessage.
 * As linhas continuam em introLines/outroLines — só a apresentação muda.
 */
class EmailViabiliza extends MailMessage
{
    /**
     * Imagens do topo embutidas no e-mail (cid:). Link externo depende do APP_URL
     * ser público e do cliente de e-mail baixar imagens; o cid sempre aparece.
     *
     * @var array<string, string>
     */
    public const IMAGENS_DO_TOPO = [
        'viabiliza-pin' => 'viabiliza-pin.png',
        'logo-prefeitura' => 'logo_prefeitura.png',
        'logo-sedur' => '01JW9M9BYJ76Y0M06Z1XHJDKH8.png',
    ];

    public function __construct()
    {
        $this->markdown('emails.notificacao');
        $this->withSymfonyMessage(static function (Email $email): void {
            self::embutirImagensDoTopo($email);
        });
    }

    public static function embutirImagensDoTopo(Email $email): void
    {
        foreach (self::IMAGENS_DO_TOPO as $cid => $arquivo) {
            $email->embedFromPath(public_path('images/'.$arquivo), $cid, 'image/png');
        }
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
