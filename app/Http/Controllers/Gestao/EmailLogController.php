<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $logs = EmailLog::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate((int) Settings::get('ui.email_logs.per_page', 20))
            ->through(fn (EmailLog $log) => [
                'id' => $log->id,
                'recipient_email' => $log->recipient_email,
                'recipient_name' => $log->recipient_name,
                'type' => EmailLog::notificationLabel($log->notification_class),
                'status' => $log->status,
                'error_message' => $log->error_message,
                'queued_at' => $log->queued_at?->toIso8601String(),
                'sent_at' => $log->sent_at?->toIso8601String(),
                'failed_at' => $log->failed_at?->toIso8601String(),
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('gestao/emails/index', [
            'logs' => $logs,
            'filters' => ['status' => $status],
            'counts' => [
                'na_fila' => EmailLog::where('status', 'na_fila')->count(),
                'enviado' => EmailLog::where('status', 'enviado')->count(),
                'falhou' => EmailLog::where('status', 'falhou')->count(),
            ],
        ]);
    }
}
