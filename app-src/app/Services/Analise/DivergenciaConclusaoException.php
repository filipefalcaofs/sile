<?php

namespace App\Services\Analise;

use DomainException;

/**
 * O resultado escolhido contradiz as marcações por atividade da ficha. Não
 * bloqueia: a tela pede confirmação e reenvia com a divergência confirmada.
 */
class DivergenciaConclusaoException extends DomainException {}
