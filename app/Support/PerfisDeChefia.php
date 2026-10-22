<?php

namespace App\Support;

/**
 * Perfis que a análise pode indicar ao encaminhar um processo para a chefia.
 */
final class PerfisDeChefia
{
    /** @var list<string> */
    public const NOMES = ['Chefe de setor', 'Subcoordenador', 'Coordenador'];
}
