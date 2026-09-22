<?php

namespace App\Services\Vistoria;

use DomainException;

/**
 * Só o vistoriador que abriu a ficha pode alterá-la (rascunho, polígono,
 * anexos, conclusão). Os demais usuários com a permissão leem — nunca editam
 * a ficha de terceiro.
 */
class InspectionAutoriaException extends DomainException {}
