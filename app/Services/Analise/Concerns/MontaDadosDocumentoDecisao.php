<?php

namespace App\Services\Analise\Concerns;

use App\Models\Company;
use App\Models\ViabilityRequest;
use App\Support\Settings;
use RuntimeException;

/**
 * Montagem compartilhada dos documentos de DECISÃO (TVL e indeferimento
 * fundamentado): dados de empresa/imóvel, assinatura parametrizável, disco
 * parametrizado NUNCA público e normalização de listas jsonb. Extraído do
 * TvlPdfService para o documento de indeferimento espelhar o TVL sem
 * duplicar regra — os dois documentos respondem à mesma decisão.
 */
trait MontaDadosDocumentoDecisao
{
    /**
     * Disco parametrizado (analise.tvl.disk) — NUNCA público: os documentos de
     * decisão são internos (CA-02). Configuração 'public' é recusada (guarda
     * anti-vazamento/LGPD).
     */
    private function resolverDisco(): string
    {
        $disk = (string) Settings::get('analise.tvl.disk', config('sile.analise.tvl.disk', 'local'));

        if ($disk === 'public') {
            throw new RuntimeException('O disco dos documentos de decisão não pode ser público (analise.tvl.disk) — os documentos são internos.');
        }

        return $disk;
    }

    /**
     * @return array<string, string|null>
     */
    private function dadosEmpresa(?Company $company): array
    {
        return [
            'razao_social' => $company?->legal_name,
            'nome_fantasia' => $company?->trade_name,
            'cnpj' => $company?->formatted_cnpj,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosImovel(?ViabilityRequest $request): array
    {
        if ($request === null) {
            return [];
        }

        $endereco = implode(', ', array_filter([
            $request->address_street,
            $request->address_number,
            $request->address_complement,
        ]));

        return [
            'endereco' => $endereco,
            'bairro' => $request->address_neighborhood,
            'cep' => $request->address_zip,
            'area_m2' => $request->used_area_m2,
        ];
    }

    /**
     * Assinatura parametrizável (RN-005): 'imagem' embute a firma digitalizada do
     * diretor (quando configurada e existente); 'nenhuma' não assina. gov.br/ICP é
     * gancho futuro (→ SEDUR) — por ora os modos efetivos são 'imagem'/'nenhuma'.
     *
     * @return array<string, mixed>|null
     */
    private function montarAssinatura(): ?array
    {
        $modo = (string) Settings::get(
            'analise.tvl.assinatura.modo',
            config('sile.analise.tvl.assinatura.modo', 'imagem'),
        );

        if ($modo === 'nenhuma') {
            return null;
        }

        $imagem = $this->resolverImagemAssinatura((string) Settings::get(
            'analise.tvl.assinatura.imagem_path',
            config('sile.analise.tvl.assinatura.imagem_path', ''),
        ));

        return ['modo' => $modo, 'imagem' => $imagem];
    }

    /**
     * Resolve a imagem da assinatura num data URI base64 (embutível no dompdf sem
     * acesso remoto). Caminho vazio/inexistente → null (sem firma forjada).
     */
    private function resolverImagemAssinatura(string $path): ?string
    {
        if (trim($path) === '') {
            return null;
        }

        foreach ([$path, base_path($path), storage_path($path), public_path($path)] as $candidato) {
            if ($candidato !== '' && is_file($candidato)) {
                $conteudo = @file_get_contents($candidato);

                if ($conteudo === false || $conteudo === '') {
                    return null;
                }

                return 'data:'.$this->detectarMimeImagem($candidato).';base64,'.base64_encode($conteudo);
            }
        }

        return null;
    }

    private function detectarMimeImagem(string $caminho): string
    {
        $mime = function_exists('mime_content_type') ? @mime_content_type($caminho) : false;

        if (is_string($mime) && str_starts_with($mime, 'image/')) {
            return $mime;
        }

        return match (strtolower((string) pathinfo($caminho, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }

    private function formatarCnae(string $code): string
    {
        return preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $code) ?? $code;
    }

    /**
     * Normaliza um campo jsonb em uma lista de strings (aceita lista de strings
     * ou de objetos com 'descricao'), descartando vazios.
     *
     * @return list<string>
     */
    private function listaDeTexto(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $itens = [];
        foreach ($valor as $entrada) {
            if (is_string($entrada) && trim($entrada) !== '') {
                $itens[] = $entrada;

                continue;
            }

            if (is_array($entrada) && isset($entrada['descricao']) && is_string($entrada['descricao']) && trim($entrada['descricao']) !== '') {
                $itens[] = $entrada['descricao'];
            }
        }

        return $itens;
    }

    /**
     * Versão de regra representativa para a coluna rules_version da auditoria
     * (RN-004 — o documento reflete a versão das regras da decisão). Espelha o
     * helper do AnalysisRecordService: primeira versão real do mapa aninhado.
     *
     * @param  array<string, mixed>|null  $rulesVersions
     */
    private function rulesVersionRepresentativa(?array $rulesVersions): ?string
    {
        foreach ($rulesVersions ?? [] as $grupo) {
            if (is_array($grupo)) {
                foreach ($grupo as $versao) {
                    if (is_string($versao) && $versao !== '') {
                        return $versao;
                    }
                }

                continue;
            }

            if (is_string($grupo) && $grupo !== '') {
                return $grupo;
            }
        }

        return null;
    }
}
