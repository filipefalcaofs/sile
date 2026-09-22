<?php

namespace App\Services\Abuso\Contracts;

use App\Services\Abuso\AbuseFinding;
use App\Services\Abuso\DetectionWindow;

/**
 * Strategy de detecção de abuso (HU-149). Cada detector é determinístico sobre
 * dado REAL (queries Eloquent, SEM IA): varre a janela parametrizável e emite
 * findings SÓ acima do limiar. Os detectores são registrados por tag
 * 'abuse.detectors' (aditiva — a 12-08 acrescenta os estruturais); o
 * AbuseDetectionService consome o iterable<AbuseDetector> resolvido por ela.
 */
interface AbuseDetector
{
    public function key(): string;

    /**
     * @return iterable<AbuseFinding>
     */
    public function detect(DetectionWindow $window): iterable;
}
