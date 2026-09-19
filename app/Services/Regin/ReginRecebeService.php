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

        return DB::transaction(function () use ($envelope, $protocolo): string {
            $existente = ReginRecebimento::query()
                ->where('protocolo', $protocolo)
                ->lockForUpdate()
                ->first();

            if ($existente !== null) {
                $this->auditar($existente, $protocolo, '5', 'duplicado');

                return '5';
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

            try {
                $processo = $this->protocolador->protocolar($recebimento);

                if ($processo !== null) {
                    $recebimento->update(['viability_request_id' => $processo->id]);
                }
            } catch (Throwable) {
                // O ack 3 é do envelope. Sem catálogo/motor, o recebimento fica
                // sem processo — nunca finge que protocolou.
            }

            $this->auditar($recebimento, $protocolo, '3', 'sucesso');

            return '3';
        });
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
