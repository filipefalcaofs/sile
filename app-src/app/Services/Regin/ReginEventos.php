<?php

namespace App\Services\Regin;

use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;

/**
 * Eventos do protocolo enviados pela JUCEB (PEV_COD_EVENTO / PEV_NOME_EVENTO),
 * como "Alteração de atividades econômicas". É o serviço legível do processo; o
 * campo `servico` do envelope é só o nome do webservice (WsProSol098).
 */
class ReginEventos
{
    /**
     * @return list<array{codigo: string, nome: string}>
     */
    public static function doRecebimento(ReginRecebimento $recebimento): array
    {
        $envelope = is_array($recebimento->envelope) ? $recebimento->envelope : [];

        foreach ([$recebimento->corpo, $envelope['json'] ?? null] as $json) {
            $eventos = self::normalizar(self::linhasDoRowset($json));

            if ($eventos !== []) {
                return $eventos;
            }
        }

        return self::normalizar(self::linhasDoXml($envelope['xml'] ?? null));
    }

    /**
     * Grava os eventos do recebimento no processo, sem repetir o código já gravado.
     */
    public static function gravar(ViabilityRequest $processo, ReginRecebimento $recebimento): int
    {
        $existentes = $processo->eventos()->pluck('codigo')->all();
        $gravados = 0;

        foreach (self::doRecebimento($recebimento) as $evento) {
            if (in_array($evento['codigo'], $existentes, true)) {
                continue;
            }

            $processo->eventos()->create($evento);
            $gravados++;
        }

        return $gravados;
    }

    /**
     * @return list<mixed>
     */
    private static function linhasDoRowset(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $rowset = $json['rowset'] ?? $json['ROWSET'] ?? null;
        $linhas = is_array($rowset) ? ($rowset['GROUPPSC_PROT_EVENTO_RFB']['PSC_PROT_EVENTO_RFB'] ?? []) : [];

        if (! is_array($linhas)) {
            return [];
        }

        return array_is_list($linhas) ? $linhas : [$linhas];
    }

    /**
     * @return list<mixed>
     */
    private static function linhasDoXml(mixed $xml): array
    {
        if (! is_string($xml) || trim($xml) === '') {
            return [];
        }

        $errosInternos = libxml_use_internal_errors(true);
        $documento = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($errosInternos);

        $nos = $documento === false ? false : $documento->xpath('//GROUPPSC_PROT_EVENTO_RFB/PSC_PROT_EVENTO_RFB');

        if ($nos === false) {
            return [];
        }

        $linhas = [];

        foreach ($nos as $no) {
            $linhas[] = [
                'PEV_COD_EVENTO' => (string) $no->PEV_COD_EVENTO,
                'PEV_NOME_EVENTO' => (string) $no->PEV_NOME_EVENTO,
            ];
        }

        return $linhas;
    }

    /**
     * @param  list<mixed>  $linhas
     * @return list<array{codigo: string, nome: string}>
     */
    private static function normalizar(array $linhas): array
    {
        $eventos = [];

        foreach ($linhas as $linha) {
            if (! is_array($linha)) {
                continue;
            }

            $codigo = trim((string) ($linha['PEV_COD_EVENTO'] ?? ''));
            $nome = self::corrigirAcentos(trim((string) ($linha['PEV_NOME_EVENTO'] ?? '')));

            if ($codigo === '' || $nome === '' || isset($eventos[$codigo])) {
                continue;
            }

            $eventos[$codigo] = ['codigo' => mb_substr($codigo, 0, 20), 'nome' => mb_substr($nome, 0, 255)];
        }

        return array_values($eventos);
    }

    /**
     * O xml da JUCEB chega com o UTF-8 lido como Latin-1 ("AlteraÃ§Ã£o"); desfaz
     * a dupla codificação só quando o resultado é UTF-8 válido.
     */
    private static function corrigirAcentos(string $texto): string
    {
        if (preg_match('/[ÃÂ]/u', $texto) !== 1) {
            return $texto;
        }

        $corrigido = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');

        return mb_check_encoding($corrigido, 'UTF-8') ? $corrigido : $texto;
    }
}
