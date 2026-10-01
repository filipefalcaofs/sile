<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'direction',
    'integration',
    'method',
    'url',
    'http_status',
    'outcome',
    'duration_ms',
    'request_headers',
    'request_body',
    'response_headers',
    'response_body',
    'error_message',
    'truncated',
    'correlation_id',
    'ip_address',
    'user_agent',
])]
class IntegrationCall extends Model
{
    public const UPDATED_AT = null;

    /**
     * Rótulo da tela. Slug desconhecido aparece como foi gravado (o host).
     *
     * @var array<string, string>
     */
    public const ROTULOS = [
        'regin' => 'REGIN / JUCEB',
        'geoserver' => 'GeoServer',
        'geocoding' => 'Geocodificação',
        'cnpj' => 'CNPJ',
        'govbr' => 'gov.br',
        'inscricao_imobiliaria' => 'Inscrição imobiliária',
        'sedur_contato' => 'SEDUR contato',
        'sedur_auth' => 'SEDUR autenticação',
        'ia' => 'IA',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'duration_ms' => 'integer',
            'request_headers' => 'array',
            'response_headers' => 'array',
            'truncated' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public static function rotulo(string $integracao): string
    {
        return self::ROTULOS[$integracao] ?? $integracao;
    }
}
