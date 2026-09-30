<?php

namespace App\Http\Controllers\Regin;

use App\Http\Controllers\Controller;
use App\Services\Regin\ReginRecebeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReginRecebeController extends Controller
{
    public function __construct(private ReginRecebeService $recebe) {}

    public function ping(): Response
    {
        return $this->codigo('3');
    }

    public function store(Request $request): Response
    {
        $inicio = hrtime(true);
        $correlacao = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();

        try {
            $codigo = $this->recebe->receive($request->all());
            $status = 200;
        } catch (InvalidArgumentException) {
            $codigo = '0';
            $status = 400;
        }

        // Sem CNPJ e sem o json: o envelope tem dado pessoal e o corpo pode
        // ser grande. O protocolo basta para achar o recebimento na trilha.
        Log::info('regin.recebe', [
            'correlacao' => $correlacao,
            'protocolo' => $request->input('protocolo'),
            'cod_funcao' => $request->input('codFuncao'),
            'servico' => $request->input('servico'),
            'codigo' => $codigo,
            'duracao_ms' => (int) ((hrtime(true) - $inicio) / 1_000_000),
            'etapa' => 'recebe',
        ]);

        return $this->codigo($codigo, $status)->header('X-Request-Id', $correlacao);
    }

    private function codigo(string $codigo, int $status = 200): Response
    {
        return response($codigo, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
