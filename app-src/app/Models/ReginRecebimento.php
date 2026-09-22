<?php

namespace App\Models;

use Database\Factories\ReginRecebimentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'protocolo',
    'cnpj_destino',
    'cnpj_empresa',
    'cnpj_origem',
    'cod_funcao',
    'nire',
    'servico',
    'data_geracao',
    'corpo',
    'envelope',
    'viability_request_id',
])]
class ReginRecebimento extends Model
{
    /** @use HasFactory<ReginRecebimentoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cod_funcao' => 'integer',
            'data_geracao' => 'datetime',
            'corpo' => 'array',
            'envelope' => 'array',
        ];
    }
}
