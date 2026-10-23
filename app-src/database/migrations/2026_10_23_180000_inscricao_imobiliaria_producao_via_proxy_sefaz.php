<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Produção da consulta de inscrição passa do BFF para o proxy-sefaz, que já
 * devolve `Utilizacao` (tipo do imóvel). Só troca o que ainda está com a URL
 * antiga de fábrica — URL editada pela gestão é preservada. Homologação segue
 * no BFF (o proxy não existe lá). Idempotente e reversível.
 */
return new class extends Migration
{
    private const CHAVES = [
        'integrations.inscricao_imobiliaria.url_producao',
        'integrations.inscricao_imobiliaria.base_url',
    ];

    private const ANTIGA = 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria';

    private const NOVA = 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-proxy-sefaz/integra/v1/inscricao-imobiliaria?valor=';

    public function up(): void
    {
        $this->trocar(self::ANTIGA, self::NOVA);
    }

    public function down(): void
    {
        $this->trocar(self::NOVA, self::ANTIGA);
    }

    private function trocar(string $de, string $para): void
    {
        DB::table('parameters')
            ->whereIn('key', self::CHAVES)
            ->where('value', $de)
            ->update(['value' => $para, 'updated_at' => now()]);

        DB::table('parameters')
            ->whereIn('key', self::CHAVES)
            ->where('default_value', $de)
            ->update(['default_value' => $para, 'updated_at' => now()]);
    }
};
