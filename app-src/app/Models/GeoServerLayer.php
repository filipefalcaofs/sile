<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\GeoServerLayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Camada WFS (FeatureType) do GeoServer SEDUR consultada na identificação da
 * zona urbanística — dado administrável (HU-014): uma zona nova da LOUOS
 * entra por cadastro, sem deploy. O par workspace+type_name é único e
 * imutável na edição; a desativação apenas a retira do escopo ativos() que o
 * GeoServerWfsZonaClient consulta. Auditada via HasAuditoria (RN-002).
 */
#[Fillable(['workspace', 'type_name', 'label', 'ativo', 'ordem'])]
class GeoServerLayer extends Model
{
    use HasAuditoria;

    /** @use HasFactory<GeoServerLayerFactory> */
    use HasFactory;

    /**
     * Nome explícito: a inflexão padrão geraria "geo_server_layers".
     */
    protected $table = 'geoserver_layers';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    /**
     * Nome completo no formato WFS: "workspace:type_name".
     */
    public function nomeCompleto(): string
    {
        return $this->workspace.':'.$this->type_name;
    }

    /**
     * @param  Builder<GeoServerLayer>  $query
     * @return Builder<GeoServerLayer>
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true)->orderBy('ordem');
    }
}
