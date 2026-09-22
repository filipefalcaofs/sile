<?php

namespace App\Services\Abuso;

use App\Enums\AbuseSeverity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Achado imutável de um detector (HU-149): a ocorrência suspeita que vira um
 * AbuseAlert. O fingerprint é a assinatura DETERMINÍSTICA e estável da ocorrência
 * (idempotência estrutural da 12-03 — 1 alerta aberto por regra/fingerprint).
 * Acima do limiar de severidade, o serviço encaminha o processo à malha fina —
 * NUNCA pune nem transiciona status (RN-001).
 */
final readonly class AbuseFinding
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public string $ruleKey,
        public AbuseSeverity $severity,
        public string $fingerprint,
        public array $evidence,
        public ?int $viabilityRequestId = null,
        public ?Model $subject = null,
        public ?CarbonInterface $windowStart = null,
        public ?CarbonInterface $windowEnd = null,
    ) {}
}
