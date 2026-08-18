<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Notifications\Events\NotificationSent;

class LogNotificationSent
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $logId = $event->notification->emailLogId ?? null;

        if ($logId) {
            EmailLog::find($logId)?->markAsSent();
        }
    }
}
