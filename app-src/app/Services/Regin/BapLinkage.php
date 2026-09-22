<?php

namespace App\Services\Regin;

use DateTimeImmutable;

/**
 * DTO imutável do vínculo BAP (Boletim de Atividade Produtiva) de um processo no
 * Regin/Junta (HU-134): o identificador do BAP e a data em que o vínculo foi
 * registrado. O prazo de atuação (bap_due_at) é DERIVADO a jusante (09-10) a
 * partir de linkedAt + o parâmetro expresso.bap.prazo_horas via
 * BusinessDeadlineCalculator — separando a política de prazo da resolução do
 * vínculo. A forma fina vem do contrato real na Fase 13.
 */
final readonly class BapLinkage
{
    public function __construct(
        public string $bapNumber,
        public DateTimeImmutable $linkedAt,
    ) {}
}
