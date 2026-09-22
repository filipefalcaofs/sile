<?php

namespace App\Enums;

/**
 * Intencao declarada para um CNAE numa solicitacao de alteracao de atividade
 * economica (RN-AA-05b). Fica no pivot `viability_request_cnaes`: `null`
 * significa que a solicitacao nao declara intencao por atividade (primeiro
 * estabelecimento, renovacao); um CNAE que a solicitacao nao menciona
 * simplesmente nao tem linha no pivot, entao nao existe caso "manter".
 */
enum IntencaoAtividade: string
{
    case Incluir = 'incluir';
    case Excluir = 'excluir';
}
