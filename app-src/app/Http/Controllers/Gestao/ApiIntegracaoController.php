<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\UpdateApiIntegracaoRequest;
use App\Models\Parameter;
use App\Services\Legado\LegadoIndisponivelException;
use App\Services\Legado\LegadoViabilidadeClient;
use App\Support\ApiIntegracaoCatalog;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ApiIntegracaoController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function edit(): Response
    {
        $aba = (string) request()->query('aba', 'regin');

        if (ApiIntegracaoCatalog::find($aba) === null) {
            $aba = 'regin';
        }

        return Inertia::render('gestao/config-apis/index', [
            'aba' => $aba,
            'abas' => array_map(fn (array $definicao): array => $this->apresentar($definicao), ApiIntegracaoCatalog::abas()),
        ]);
    }

    public function update(UpdateApiIntegracaoRequest $request, string $aba): RedirectResponse
    {
        $definicao = ApiIntegracaoCatalog::find($aba);

        abort_if($definicao === null, 404);

        /** @var array<string, string|null> $campos */
        $campos = $request->validated('campos');

        foreach ($definicao['campos'] as $campo) {
            if (! array_key_exists($campo['id'], $campos)) {
                continue;
            }

            $valor = $campos[$campo['id']];

            if ($campo['tipo'] === 'senha' && ($valor === null || $valor === '')) {
                continue;
            }

            $this->persist($campo['key'], $valor === null ? '' : (string) $valor);
        }

        $this->espelharUrlAtiva($definicao, $campos);

        return redirect()
            ->route('gestao.config-apis.edit', ['aba' => $aba])
            ->with('status', 'Configuração de '.$definicao['nome'].' atualizada.');
    }

    /**
     * Teste de conexão da API do Simplifica: uma consulta de leitura com a URL
     * e a credencial gravadas. Falha aparece como falha.
     */
    public function testarLegado(LegadoViabilidadeClient $legado): RedirectResponse
    {
        $url = (string) Settings::get('integrations.legado_api.base_url', '');

        try {
            $legado->testarConexao();
            $erro = null;
        } catch (LegadoIndisponivelException $e) {
            $erro = $e->getMessage();
        }

        $this->audit->log(
            'config-apis',
            'testar-legado',
            'Teste de conexão da API do Simplifica (legado)',
            ['url' => $url],
            result: $erro === null ? 'sucesso' : 'falha',
        );

        if ($erro !== null) {
            return back()->withErrors(['legado_connection' => $erro]);
        }

        return back()->with('status', 'Conexão com a API do Simplifica funcionando.');
    }

    /**
     * @param  array<string, mixed>  $definicao
     * @param  array<string, string|null>  $campos
     */
    private function espelharUrlAtiva(array $definicao, array $campos): void
    {
        $espelho = $definicao['espelhar_url_ativa'];

        if (! is_array($espelho)) {
            return;
        }

        $producao = ($campos[$espelho['interruptor']] ?? '0') === '1';
        $origem = $producao ? $espelho['producao'] : $espelho['homologacao'];

        if (! isset($campos[$origem]) || $campos[$origem] === null || $campos[$origem] === '') {
            return;
        }

        $this->persist($espelho['destino'], (string) $campos[$origem]);
    }

    private function persist(string $key, string $value): void
    {
        $parameter = Parameter::query()->where('key', $key)->firstOrFail();

        if ((string) ($parameter->value ?? '') === $value) {
            return;
        }

        $old = $parameter->value;
        $parameter->update(['value' => $value]);

        $this->audit->log(
            'parametros',
            'parametro-alterado',
            "Parâmetro alterado: {$parameter->auditLabel()}",
            [
                'key' => $parameter->key,
                'valor_anterior' => $parameter->sensitive ? '[criptografado]' : $old,
                'valor_novo' => $parameter->sensitive ? '[criptografado]' : $value,
            ],
            $parameter,
        );
    }

    /**
     * @param  array<string, mixed>  $definicao
     * @return array<string, mixed>
     */
    private function apresentar(array $definicao): array
    {
        $campos = [];

        foreach ($definicao['campos'] as $campo) {
            $parameter = Parameter::query()->where('key', $campo['key'])->first();
            $sensivel = (bool) $parameter?->sensitive;
            $temValor = $parameter?->getRawOriginal('value') !== null && $parameter?->getRawOriginal('value') !== '';
            $bruto = $parameter?->value ?? $parameter?->default_value;

            $campos[] = [
                'id' => $campo['id'],
                'rotulo' => $campo['rotulo'],
                'tipo' => $campo['tipo'],
                'opcoes' => $campo['opcoes'],
                'ajuda' => $parameter?->description,
                'tem_valor' => $sensivel && $temValor,
                'valor' => match ($campo['tipo']) {
                    'senha' => null,
                    'interruptor' => (bool) $parameter?->typedValue(),
                    default => $bruto,
                },
            ];
        }

        return [
            'id' => $definicao['id'],
            'nome' => $definicao['nome'],
            'descricao' => $definicao['descricao'],
            'teste' => $definicao['teste'],
            'campos' => $campos,
        ];
    }
}
