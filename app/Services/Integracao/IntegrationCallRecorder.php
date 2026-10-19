<?php

namespace App\Services\Integracao;

use App\Models\IntegrationCall;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Grava cada chamada HTTP de integração, nos dois sentidos. Falha ao gravar
 * não pode derrubar a chamada: o REGIN precisa do ack, e o cliente externo
 * precisa do erro real da rede.
 */
class IntegrationCallRecorder
{
    /**
     * Segredos que nunca aparecem, nem em parte: redigidos por completo.
     *
     * @var list<string>
     */
    private const TERMOS_TOTAIS = [
        'password',
        'senha',
        'passwd',
        'secret',
    ];

    /**
     * Credenciais de transporte (token, JWT, chave de API, cookie): mascaradas
     * mostrando só o começo, o bastante para auditar qual credencial foi usada
     * sem expor o valor. A detecção é por conteúdo do nome, não por igualdade:
     * cabeçalhos como "jwt-token" ou "x-auth-token" também entram.
     *
     * @var list<string>
     */
    private const TERMOS_PARCIAIS = [
        'token',
        'jwt',
        'auth',
        'api_key',
        'apikey',
        'api-key',
        'cookie',
    ];

    public function saidaRespondida(ClientRequest $request, ClientResponse $response): void
    {
        $stats = $response->handlerStats();
        $segundos = $stats['total_time'] ?? null;
        $truncou = false;

        $this->gravar([
            'direction' => 'saida',
            'integration' => $this->integracao($request->url()),
            'method' => strtoupper($request->method()),
            'url' => $this->urlSegura($request->url()),
            'http_status' => $response->status(),
            'outcome' => $response->successful() ? 'sucesso' : 'erro',
            'duration_ms' => is_numeric($segundos) ? (int) round(((float) $segundos) * 1000) : null,
            'request_headers' => $this->cabecalhos($request->headers()),
            'request_body' => $this->corpo($this->corpoCliente($request), $truncou),
            'response_headers' => $this->cabecalhos($response->headers()),
            'response_body' => $this->corpo($response->body(), $truncou),
            'error_message' => null,
            'truncated' => $truncou,
            'correlation_id' => $this->correlacao($request->headers()),
            'ip_address' => null,
            'user_agent' => $this->agente($request->headers()),
        ]);
    }

    public function saidaSemConexao(ClientRequest $request, Throwable $exception): void
    {
        $truncou = false;

        $this->gravar([
            'direction' => 'saida',
            'integration' => $this->integracao($request->url()),
            'method' => strtoupper($request->method()),
            'url' => $this->urlSegura($request->url()),
            'http_status' => null,
            'outcome' => 'erro',
            'duration_ms' => null,
            'request_headers' => $this->cabecalhos($request->headers()),
            'request_body' => $this->corpo($this->corpoCliente($request), $truncou),
            'response_headers' => null,
            'response_body' => null,
            'error_message' => $this->mensagem($exception),
            'truncated' => $truncou,
            'correlation_id' => $this->correlacao($request->headers()),
            'ip_address' => null,
            'user_agent' => $this->agente($request->headers()),
        ]);
    }

    public function entrada(Request $request, ?Response $response, int $inicio, ?Throwable $exception = null): void
    {
        $status = $response?->getStatusCode();
        $conteudo = $response?->getContent();
        $truncou = false;

        $this->gravar([
            'direction' => 'entrada',
            'integration' => $this->integracao($request->fullUrl()),
            'method' => strtoupper($request->method()),
            'url' => $this->urlSegura($request->fullUrl()),
            'http_status' => $status,
            'outcome' => $exception === null && is_int($status) && $status >= 200 && $status < 300 ? 'sucesso' : 'erro',
            'duration_ms' => (int) ((hrtime(true) - $inicio) / 1_000_000),
            'request_headers' => $this->cabecalhos($request->headers->all()),
            'request_body' => $this->corpo($request->getContent(), $truncou),
            'response_headers' => $response !== null ? $this->cabecalhos($response->headers->all()) : null,
            'response_body' => $this->corpo(is_string($conteudo) ? $conteudo : null, $truncou),
            'error_message' => $exception !== null ? $exception::class : null,
            'truncated' => $truncou,
            'correlation_id' => $request->headers->get('X-Request-Id'),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
        ]);
    }

    public function integracao(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $alvo = $host.$path;

        $regras = [
            'regin' => ['api_integracao', 'juceb', 'regin'],
            'geoserver' => ['geoserver', '/wfs'],
            'geocoding' => ['nominatim'],
            'cnpj' => ['brasilapi', '/cnpj/'],
            'govbr' => ['acesso.gov.br'],
            'inscricao_imobiliaria' => ['inscricao-imobiliaria'],
            'sedur_contato' => ['ws-contato'],
            'sedur_auth' => ['ws-autenticacao', 'ws-gp'],
            'ia' => ['openai'],
        ];

        foreach ($regras as $nome => $trechos) {
            foreach ($trechos as $trecho) {
                if (str_contains($alvo, $trecho)) {
                    return $nome;
                }
            }
        }

        return $host !== '' ? $host : 'desconhecida';
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function gravar(array $atributos): void
    {
        try {
            IntegrationCall::query()->create($atributos);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function corpoCliente(ClientRequest $request): ?string
    {
        $stream = $request->toPsrRequest()->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $texto = (string) $stream;

        if ($texto === '' && $request->data() !== []) {
            $codificado = json_encode($request->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $texto = is_string($codificado) ? $codificado : '';
        }

        return $texto === '' ? null : $texto;
    }

    /**
     * @param  array<string, mixed>  $cabecalhos
     * @return array<string, string>
     */
    private function cabecalhos(array $cabecalhos): array
    {
        $limpos = [];

        foreach ($cabecalhos as $nome => $valores) {
            $chave = (string) $nome;
            $lista = is_array($valores) ? $valores : [$valores];
            $nivel = $this->nivelDeSigilo($chave);

            if ($nivel === 'total') {
                $limpos[$chave] = '[redigido]';

                continue;
            }

            $texto = implode(', ', array_map(
                fn (mixed $valor): string => is_scalar($valor) ? (string) $valor : '[omitido]',
                $lista,
            ));

            $limpos[$chave] = $nivel === 'parcial' ? $this->mascarar($texto) : $texto;
        }

        return $limpos;
    }

    /**
     * @param  array<string, mixed>  $cabecalhos
     */
    private function correlacao(array $cabecalhos): ?string
    {
        foreach ($cabecalhos as $nome => $valores) {
            if (strtolower((string) $nome) !== 'x-request-id') {
                continue;
            }

            $lista = is_array($valores) ? $valores : [$valores];
            $valor = $lista[0] ?? null;

            return is_scalar($valor) && (string) $valor !== '' ? (string) $valor : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $cabecalhos
     */
    private function agente(array $cabecalhos): ?string
    {
        foreach ($cabecalhos as $nome => $valores) {
            if (strtolower((string) $nome) !== 'user-agent') {
                continue;
            }

            $lista = is_array($valores) ? $valores : [$valores];
            $valor = $lista[0] ?? null;

            if (! is_scalar($valor) || (string) $valor === '') {
                return null;
            }

            return substr((string) $valor, 0, 500);
        }

        return null;
    }

    private function corpo(?string $texto, bool &$truncou): ?string
    {
        if ($texto === null || $texto === '') {
            return null;
        }

        $redigido = $this->redigirTexto($texto);
        $limite = max(1, (int) config('viabiliza.integrations.log.limite_corpo', 500_000));

        if (strlen($redigido) <= $limite) {
            return $redigido;
        }

        $truncou = true;

        return substr($redigido, 0, $limite);
    }

    private function redigirTexto(string $texto): string
    {
        $json = json_decode($texto, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $limpo = $this->redigir($json);

            if ($limpo === $json) {
                return $texto;
            }

            $codificado = json_encode($limpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return is_string($codificado) ? $codificado : $texto;
        }

        if (! str_contains($texto, '=')) {
            return $texto;
        }

        parse_str($texto, $formulario);

        if ($formulario === []) {
            return $texto;
        }

        $limpo = $this->redigir($formulario);

        if ($limpo === $formulario) {
            return $texto;
        }

        return http_build_query($limpo);
    }

    private function redigir(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }

        $saida = [];

        foreach ($valor as $chave => $item) {
            $nivel = is_string($chave) ? $this->nivelDeSigilo($chave) : null;

            if ($nivel === 'total') {
                $saida[$chave] = '[redigido]';

                continue;
            }

            if ($nivel === 'parcial' && is_scalar($item)) {
                $saida[$chave] = $this->mascarar((string) $item);

                continue;
            }

            $saida[$chave] = is_array($item) ? $this->redigir($item) : $item;
        }

        return $saida;
    }

    /**
     * @return 'total'|'parcial'|null
     */
    private function nivelDeSigilo(string $chave): ?string
    {
        $alvo = strtolower($chave);

        foreach (self::TERMOS_TOTAIS as $termo) {
            if (str_contains($alvo, $termo)) {
                return 'total';
            }
        }

        foreach (self::TERMOS_PARCIAIS as $termo) {
            if (str_contains($alvo, $termo)) {
                return 'parcial';
            }
        }

        return null;
    }

    /**
     * Mantém só os primeiros caracteres e troca o resto por ****. Nunca expõe o
     * fim do valor (num JWT, a assinatura). Valor curto some por inteiro.
     */
    private function mascarar(string $valor): string
    {
        $valor = trim($valor);

        if (strlen($valor) <= 8) {
            return '****';
        }

        return substr($valor, 0, 4).'****';
    }

    private function urlSegura(string $url): string
    {
        $partes = parse_url($url);

        if (! is_array($partes) || ! isset($partes['host'])) {
            return $url;
        }

        $montada = ($partes['scheme'] ?? 'https').'://'.$partes['host'];

        if (isset($partes['port'])) {
            $montada .= ':'.$partes['port'];
        }

        $montada .= $partes['path'] ?? '';

        if (! isset($partes['query'])) {
            return $montada;
        }

        parse_str($partes['query'], $consulta);
        $consulta = $this->redigir($consulta);

        if (! is_array($consulta) || $consulta === []) {
            return $montada;
        }

        return $montada.'?'.http_build_query($consulta);
    }

    private function mensagem(Throwable $exception): string
    {
        $texto = trim($exception->getMessage());

        if ($texto === '') {
            $texto = $exception::class;
        }

        return substr($texto, 0, 500);
    }
}
