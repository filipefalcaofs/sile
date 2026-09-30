<?php

namespace App\Services\Regin;

use App\Models\ReginRecebimento;
use App\Support\Audit\AuditService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ReginRecebeService
{
    public function __construct(
        private AuditService $audit,
        private ReginProcessoProtocolador $protocolador,
    ) {}

    /**
     * Persiste o envelope e devolve o código do guia (3 recebido, 5 duplicado).
     * O ack só sai depois do commit — sem persistência, não há 3.
     *
     * @param  array<string, mixed>  $envelope
     */
    public function receive(array $envelope): string
    {
        $protocolo = trim((string) ($envelope['protocolo'] ?? ''));

        if ($protocolo === '') {
            throw new InvalidArgumentException('Protocolo obrigatório.');
        }

        $recebimento = DB::transaction(function () use ($envelope, $protocolo): ?ReginRecebimento {
            $existente = ReginRecebimento::query()
                ->where('protocolo', $protocolo)
                ->lockForUpdate()
                ->first();

            if ($existente !== null) {
                $this->auditar($existente, $protocolo, '5', 'duplicado');

                return null;
            }

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

            $this->auditar($recebimento, $protocolo, '3', 'sucesso');

            return $recebimento;
        });

        if ($recebimento === null) {
            return '5';
        }

        $this->protocolar($recebimento);

        return '3';
    }

    /**
     * Abre o processo DEPOIS do commit do envelope. Fora da transação por
     * necessidade, não por estilo: a protocolação consulta o Cadastro
     * Imobiliário e o GeoServer e escreve em várias tabelas — no PostgreSQL,
     * uma query que falhe aborta a transação inteira, e a auditoria seguinte
     * derrubaria o ack com 500, desfazendo o recebimento já reconhecido.
     *
     * A falha fica REGISTRADA (log da exceção + auditoria com resultado
     * `falha`): o recebimento sem processo é pendência visível, nunca silêncio.
     */
    private function protocolar(ReginRecebimento $recebimento): void
    {
        try {
            $processo = $this->protocolador->protocolar($recebimento);

            if ($processo !== null) {
                $recebimento->update(['viability_request_id' => $processo->id]);
            }
        } catch (Throwable $exception) {
            report($exception);

            // Só a classe da exceção nas propriedades: a mensagem pode carregar
            // valores do envelope (dado pessoal). O detalhe fica no log.
            $this->audit->log(
                'regin',
                'protocolar',
                'Processo recebido via REGIN não pôde ser aberto',
                [
                    'protocolo' => $recebimento->protocolo,
                    'excecao' => $exception::class,
                ],
                $recebimento,
                'falha',
                personalData: false,
            );
        }
    }

    private function auditar(ReginRecebimento $recebimento, string $protocolo, string $codigo, string $resultado): void
    {
        $this->audit->log(
            'regin',
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

        return Carbon::parse($valor);
    }
}
