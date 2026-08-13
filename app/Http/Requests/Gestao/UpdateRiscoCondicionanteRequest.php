<?php

namespace App\Http\Requests\Gestao;

/**
 * Edição de uma condicionante-pergunta de risco (HU-019). As regras de
 * validação e a normalização são idênticas ao cadastro (mesma estrutura de
 * pergunta + reclassificação); a autorização é o middleware permission:
 * manter-cnaes da rota.
 */
class UpdateRiscoCondicionanteRequest extends StoreRiscoCondicionanteRequest {}
