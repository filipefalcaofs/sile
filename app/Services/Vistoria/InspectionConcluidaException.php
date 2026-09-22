<?php

namespace App\Services\Vistoria;

use DomainException;

/**
 * A ficha concluída é imutável: o parecer registrado na conclusão é a peça
 * oficial da vistoria. Qualquer escrita posterior é recusada com 422 no
 * controller — nunca edição silenciosa.
 */
class InspectionConcluidaException extends DomainException {}
