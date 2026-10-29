<?php

namespace Tests\Support\Sefaz;

use App\Models\Parameter;
use Illuminate\Support\Facades\Cache;

/**
 * Liga a comparação com o legado Simplifica pelos parâmetros da aba Simplifica (legado), como a tela de APIs faz.
 */
trait ConfiguraLegadoSimplifica
{
    protected function configurarLegado(bool $habilitada = true, ?string $url = 'http://legado.test', ?string $token = 'lg_teste'): void
    {
        $valores = [
            'features.legado_simplifica' => $habilitada ? '1' : '0',
            'integrations.legado_api.base_url' => $url,
            'integrations.legado_api.token' => $token,
        ];

        foreach ($valores as $chave => $valor) {
            Parameter::query()->where('key', $chave)->firstOrFail()->update(['value' => $valor]);
        }

        Cache::flush();
    }
}
