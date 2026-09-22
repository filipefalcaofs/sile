<?php

namespace App\Services\Ai;

use App\Models\Activity;
use App\Support\Audit\AuditService;
use Illuminate\Database\Eloquent\Model;

/**
 * Auditoria de CHAMADA de IA (RN-002). Padroniza o registro de toda invocação
 * de uma função de IA — sucesso, falha ou no-op por toggle desligado — sob o
 * logName 'ia' e o evento ia-{função}, fixando a versão do prompt como
 * rules_version e a marca de acesso a dado pessoal (LGPD).
 *
 * Segurança (anti-vazamento): este helper NUNCA acrescenta a api_key nem a saída
 * com PII — apenas transporta as propriedades seguras (provider/model/versão do
 * prompt/tokens/custo/ai_suggestion_id/result) que a camada chamadora decidiu
 * logar. A minimização do que vai para `properties` é responsabilidade do job.
 */
class AiCallAuditor
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $function,
        string $promptVersion,
        array $properties = [],
        ?Model $subject = null,
        string $result = 'sucesso',
        bool $personalData = false,
    ): Activity {
        return $this->audit->log(
            logName: 'ia',
            event: "ia-{$function}",
            description: "Chamada de IA ({$function}) — resultado: {$result}",
            properties: $properties,
            subject: $subject,
            result: $result,
            rulesVersion: $promptVersion,
            personalData: $personalData,
        );
    }
}
