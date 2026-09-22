<?php

namespace App\Services\Vistoria;

use DomainException;

/**
 * O processo já tem responsável atribuído (caixa do setor): só ele abre a
 * ficha de vistoria. Sem responsável, quem abre primeiro assume a vistoria.
 */
class InspectionAtribuidaException extends DomainException {}
