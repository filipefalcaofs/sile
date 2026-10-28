<?php

namespace App\Console\Commands;

use App\Services\Realty\InscricaoImobiliariaIntegrationSettings;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Diagnóstico da API de inscrição imobiliária a partir do pod: chama a URL
 * sem retry (mostra status, tempo e erro de conexão) e depois passa pelo
 * mesmo serviço que o Viabiliza usa. Só leitura; não grava nada e não imprime
 * dados de proprietário.
 */
class InscricaoImobiliariaTestarCommand extends Command
{
    protected $signature = 'inscricao:testar
        {inscricoes* : Inscrições imobiliárias (ex.: 1729950 4186370)}
        {--ambiente=ativo : ativo (configurado no painel), hml, prd ou ambos}
        {--tentativas=1 : Quantas vezes repetir cada chamada, para ver intermitência}';

    protected $description = 'Testa a API de inscrição imobiliária (SEFAZ via SEDUR) a partir do pod';

    public function handle(InscricaoImobiliariaIntegrationSettings $settings, PropertyRegistryLookup $lookup): int
    {
        $this->line('Integração cadastro_imobiliario habilitada: '.(Settings::enabled('cadastro_imobiliario') ? 'sim' : 'NÃO'));
        $this->line('Ambiente ativo no painel: '.($settings->usingProduction() ? 'produção' : 'homologação'));
        $this->line('URL ativa: '.$settings->baseUrl());
        $this->newLine();

        $urls = $this->urls($settings);
        $tentativas = max(1, (int) $this->option('tentativas'));
        $falhas = 0;

        foreach ($this->argument('inscricoes') as $inscricao) {
            $digitos = preg_replace('/\D/', '', (string) $inscricao) ?? '';

            foreach ($urls as $rotulo => $base) {
                for ($i = 1; $i <= $tentativas; $i++) {
                    $falhas += $this->chamar($rotulo, $base, $digitos, $i) ? 0 : 1;
                }
            }

            $falhas += $this->resolver($lookup, $digitos) ? 0 : 1;
            $this->newLine();
        }

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, string>
     */
    private function urls(InscricaoImobiliariaIntegrationSettings $settings): array
    {
        $hml = (string) config('viabiliza.integrations.inscricao_imobiliaria.url_homologacao');
        $prd = (string) config('viabiliza.integrations.inscricao_imobiliaria.url_producao');

        return match ($this->option('ambiente')) {
            'hml' => ['hml' => $hml],
            'prd' => ['prd' => $prd],
            'ambos' => ['hml' => $hml, 'prd' => $prd],
            default => [($settings->usingProduction() ? 'prd' : 'hml').' (ativo)' => $settings->baseUrl()],
        };
    }

    private function chamar(string $rotulo, string $base, string $digitos, int $tentativa): bool
    {
        $inicio = microtime(true);

        try {
            $response = Http::timeout((int) config('viabiliza.integrations.inscricao_imobiliaria.timeout', 12))
                ->connectTimeout(3)
                ->acceptJson()
                ->get(InscricaoImobiliariaIntegrationSettings::urlDaConsulta($base, $digitos));
        } catch (ConnectionException $e) {
            $this->error(sprintf('[%s] %s #%d: sem resposta em %s ms — %s', $rotulo, $digitos, $tentativa, $this->ms($inicio), $e->getMessage()));

            return false;
        }

        $corpo = $response->json();
        $codigo = is_array($corpo) ? ($corpo['CodigoRetorno'] ?? '—') : '—';
        $mensagem = is_array($corpo) ? ($corpo['MensagemRetorno'] ?? $corpo['Mensagem'] ?? $corpo['message'] ?? '') : mb_substr((string) $response->body(), 0, 200);
        $linha = sprintf(
            '[%s] %s #%d: HTTP %d em %s ms, CodigoRetorno=%s %s',
            $rotulo,
            $digitos,
            $tentativa,
            $response->status(),
            $this->ms($inicio),
            $codigo,
            is_scalar($mensagem) ? $mensagem : '',
        );

        $ok = $response->successful() && $codigo === '0';
        $ok ? $this->info($linha) : $this->warn($linha);

        return $ok;
    }

    private function resolver(PropertyRegistryLookup $lookup, string $digitos): bool
    {
        $inicio = microtime(true);

        try {
            $resultado = $lookup->resolve($digitos);
        } catch (PropertyNotFoundException) {
            $this->warn("[serviço] {$digitos}: não encontrada ({$this->ms($inicio)} ms)");

            return false;
        } catch (PropertyRegistryUnavailableException) {
            $this->error("[serviço] {$digitos}: INDISPONÍVEL, é o caso que o REGIN mostra ({$this->ms($inicio)} ms)");

            return false;
        }

        $this->info(sprintf(
            '[serviço] %s: ok em %s ms — %s, bairro %s, lat/lng %s,%s',
            $digitos,
            $this->ms($inicio),
            $resultado->cadastro->endereco ?? 'sem endereço',
            $resultado->cadastro->bairro ?? '—',
            $resultado->latitude ?? '—',
            $resultado->longitude ?? '—',
        ));

        return true;
    }

    private function ms(float $inicio): string
    {
        return (string) round((microtime(true) - $inicio) * 1000);
    }
}
