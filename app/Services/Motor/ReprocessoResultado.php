<?php

namespace App\Services\Motor;

use App\Enums\EtapaMotor;

final readonly class ReprocessoResultado
{
    public function __construct(
        public bool $reprocessou,
        public ?EtapaMotor $paradaEm,
        public string $statusDoProcesso,
        public string $mensagem,
    ) {}
}
