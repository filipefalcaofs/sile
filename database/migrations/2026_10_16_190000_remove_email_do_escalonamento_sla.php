<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEY = 'notificacoes.mapa_canais';

    public function up(): void
    {
        $this->ajustar(['in_app']);
    }

    public function down(): void
    {
        $this->ajustar(['email', 'in_app']);
    }

    /**
     * @param  list<string>  $canais
     */
    private function ajustar(array $canais): void
    {
        $parametro = DB::table('parameters')->where('key', self::KEY)->first();

        if ($parametro === null) {
            return;
        }

        $atualizacao = [];

        foreach (['value', 'default_value'] as $coluna) {
            $mapa = json_decode((string) $parametro->{$coluna}, true);

            if (! is_array($mapa)) {
                continue;
            }

            $mapa['escalonamento_sla'] = $canais;
            $atualizacao[$coluna] = json_encode($mapa);
        }

        if ($atualizacao !== []) {
            DB::table('parameters')->where('key', self::KEY)->update($atualizacao);
        }
    }
};
