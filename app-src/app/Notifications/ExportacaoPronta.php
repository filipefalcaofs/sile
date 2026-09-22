<?php

namespace App\Notifications;

use App\Models\ExportFile;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Aviso de "exportação pronta" (HU-131). É uma Notification STANDALONE (canais
 * `database` + `mail`) — NÃO usa o NotificationDispatcher, que é process-bound
 * (exige um viabilityRequestId): uma exportação não é um processo. Surge na
 * central de notificações (canal database) e por e-mail, com o link de download
 * por URL TEMPORÁRIA ASSINADA para a rota `gestao.relatorios.exportacoes.download`
 * (servida do disco não-público — a rota e o endpoint nascem em 15-09).
 */
class ExportacaoPronta extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ExportFile $exportFile) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua exportação está pronta')
            ->greeting('Olá!')
            ->line('A exportação que você solicitou foi gerada e está disponível para download.')
            ->line("Formato: {$this->exportFile->format} — {$this->exportFile->row_count} registro(s).")
            ->action('Baixar exportação', $this->downloadUrl())
            ->line('O link de download expira por segurança.');
    }

    /**
     * Conteúdo da notificação in-app (canal database) — base da central de
     * notificações: o arquivo pronto e o link de download assinado.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'exportacao-pronta',
            'export_file_id' => $this->exportFile->id,
            'format' => $this->exportFile->format,
            'row_count' => $this->exportFile->row_count,
            'filename' => $this->exportFile->filename,
            'title' => 'Exportação pronta',
            'message' => "A sua exportação ({$this->exportFile->format}) está disponível para download.",
            'url' => $this->downloadUrl(),
        ];
    }

    /**
     * Link de download por URL temporária assinada (TTL parametrizável). A rota
     * `gestao.relatorios.exportacoes.download` é criada em 15-09; até lá esta
     * notificação só é exercitada com Notification::fake nos testes.
     */
    private function downloadUrl(): string
    {
        $ttl = (int) Settings::get(
            'relatorios.export.download.ttl_minutos',
            config('sile.relatorios.export.download.ttl_minutos', 15),
        );

        return URL::temporarySignedRoute(
            'gestao.relatorios.exportacoes.download',
            now()->addMinutes($ttl),
            ['exportFile' => $this->exportFile->id],
        );
    }
}
