<?php

namespace Tests\Support\Sefaz;

use App\Models\Parameter;
use Illuminate\Support\Facades\Cache;

/**
 * Liga a API do legado Simplifica pelos parâmetros, como a tela de APIs faz.
 */
trait ConfiguraLegadoSimplifica
{
    protected function configurarLegado(bool $habilitada = true, ?string $url = 'http://legado.test', ?string $token = 'lg_teste'): void
    {
        $valores = [
            'features.legado_simplifica' => $habilitada ? '1' : '0',
            'integrations.legado_simplifica.url' => $url,
            'integrations.legado_simplifica.token' => $token,
        ];

        foreach ($valores as $chave => $valor) {
            Parameter::query()->where('key', $chave)->firstOrFail()->update(['value' => $valor]);
        }

        Cache::flush();
    }
}
