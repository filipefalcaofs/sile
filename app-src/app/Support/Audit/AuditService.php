<?php

namespace App\Support\Audit;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro explícito de auditoria (RN-002) para serviços e fluxos que não
 * passam por model events: ação, resultado e versão de regras.
 *
 * O enriquecimento de origem (ip/user_agent/channel/acting_for) acontece na
 * RecordActivityAction; o forceFill pós-log apenas sobrepõe resultado, versão
 * de regras e a marca de acesso a dado pessoal (LGPD) sem depender da API
 * interna do builder v5.
 */
class AuditService
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $logName,
        string $event,
        string $description,
        array $properties = [],
        ?Model $subject = null,
        string $result = 'sucesso',
        ?string $rulesVersion = null,
        bool $personalData = false,
    ): Activity {
        $logger = activity($logName)->withProperties($properties)->event($event);

        if (auth()->check()) {
            $logger->causedBy(auth()->user());
        }

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        /** @var Activity $activity */
        $activity = $logger->log($description);

        $activity->forceFill([
            'result' => $result,
            'rules_version' => $rulesVersion,
            'personal_data' => $personalData,
        ])->save();

        return $activity;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function logBlocked(string $logName, string $description, array $properties = []): Activity
    {
        return $this->log($logName, 'acesso-negado', $description, $properties, result: 'bloqueado');
    }
}
