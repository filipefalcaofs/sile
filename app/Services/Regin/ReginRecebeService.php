<?php

namespace App\Services\Regin;

use App\Jobs\ProtocolarRecebimentoReginJob;
use App\Models\ReginRecebimento;
use App\Support\Audit\AuditService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

class ReginRecebeService
{
    public function __construct(private AuditService $audit) {}

    /**
     * Persiste o envelope e devolve o código do guia (3 recebido, 5 duplicado).
     * O ack só sai depois de gravar — sem persistência, não há 3.
     *
     * Quem arbitra a duplicidade é o índice único de `protocolo`, não o SELECT:
     * `lockForUpdate` não trava linha inexistente, então dois envios simultâneos
     * do mesmo protocolo passam os dois pela verificação. A consulta prévia
     * resolve o caso comum; a violação do índice resolve a corrida — e também
     * significa duplicado (5), nunca erro.
     *
     * Sem transação envolvendo o conjunto: o INSERT já é atômico e a auditoria
     * que vem depois não pode desfazer um recebimento que o REGIN considera
     * entregue.
     *
     * @param  array<string, mixed>  $envelope
     */
    public function receive(array $envelope): string
    {
        $protocolo = trim((string) ($envelope['protocolo'] ?? ''));

        if ($protocolo === '') {
            throw new InvalidArgumentException('Protocolo obrigatório.');
        }

        $existente = ReginRecebimento::query()->where('protocolo', $protocolo)->first();

        if ($existente !== null) {
            $this->auditar($existente, $protocolo, '5', 'duplicado');

            return '5';
        }

        try {
            $recebimento = ReginRecebimento::query()->create([
                'protocolo' => $protocolo,
                'cnpj_destino' => $this->texto($envelope['cnpjDestino'] ?? null),
                'cnpj_empresa' => $this->texto($envelope['cnpjEmpresa'] ?? null),
                'cnpj_origem' => $this->texto($envelope['cnpjOrigem'] ?? null),
                'cod_funcao' => $this->inteiro($envelope['codFuncao'] ?? null),
                'nire' => $this->texto($envelope['nire'] ?? null),
                'servico' => $this->texto($envelope['servico'] ?? null),
                'data_geracao' => $this->data($envelope['dataGeracao'] ?? null),
                'corpo' => is_array($envelope['json'] ?? null) ? $envelope['json'] : null,
                'envelope' => $envelope,
            ]);
        } catch (UniqueConstraintViolationException) {
            $concorrente = ReginRecebimento::query()->where('protocolo', $protocolo)->first();

            if ($concorrente === null) {
                throw new InvalidArgumentException('Protocolo obrigatório.');
            }

            $this->auditar($concorrente, $protocolo, '5', 'duplicado');

            return '5';
        }

        $this->auditar($recebimento, $protocolo, '3', 'sucesso');
        $this->enfileirarProtocolacao($recebimento);

        return '3';
    }

    /**
     * A abertura do processo consulta o Cadastro Imobiliário e percorre as
     * camadas do GeoServer — ~10s medidos no ambiente real. O ack do guia é do
     * ENVELOPE, então esse trabalho vai para a fila, como já faz o protocolo do
     * portal.
     *
     * O envelope já está gravado quando chegamos aqui, então o 3 é verdadeiro
     * mesmo que o enfileiramento falhe (fila indisponível, ou conexão `sync`
     * propagando a exceção do próprio job). Nesse caso o recebimento fica sem
     * processo — pendência REGISTRADA, nunca silêncio, e nunca um 500 para o
     * REGIN por algo que não é do recebimento.
     */
    private function enfileirarProtocolacao(ReginRecebimento $recebimento): void
    {
        try {
            ProtocolarRecebimentoReginJob::dispatch($recebimento->id);
        } catch (Throwable $exception) {
            report($exception);

            // Só a classe da exceção: a mensagem pode carregar valores do
            // envelope (dado pessoal). O detalhe fica no log do report().
            $this->registrar(
                'protocolar',
                'Processo recebido via REGIN não pôde ser aberto',
                [
                    'protocolo' => $recebimento->protocolo,
                    'excecao' => $exception::class,
                ],
                $recebimento,
                'falha',
            );
        }
    }

    private function auditar(ReginRecebimento $recebimento, string $protocolo, string $codigo, string $resultado): void
    {
        $this->registrar(
            'recebe',
            'Processo recebido via REGIN',
            [
                'protocolo' => $protocolo,
                'codigo' => $codigo,
            ],
            $recebimento,
            $resultado,
            personalData: true,
        );
    }

    /**
     * A trilha nunca pode derrubar o ack. O envelope (ou a duplicidade) já foi
     * decidido quando a auditoria roda; se a trilha falhar, o REGIN ainda
     * precisa receber 3 ou 5 — senão ele reenvia e a Junta registra erro de
     * um recebimento que já está gravado.
     *
     * @param  array<string, mixed>  $properties
     */
    private function registrar(
        string $evento,
        string $descricao,
        array $properties,
        ReginRecebimento $recebimento,
        string $resultado,
        bool $personalData = false,
    ): void {
        try {
            $this->audit->log(
                'regin',
                $evento,
                $descricao,
                $properties,
                $recebimento,
                $resultado,
                personalData: $personalData,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private function inteiro(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }

    private function data(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
