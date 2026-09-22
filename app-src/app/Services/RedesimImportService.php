<?php

namespace App\Services;

use App\Enums\CompanySource;
use App\Models\Cnae;
use App\Models\Company;
use App\Rules\ValidCnpj;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Import de dados empresariais no formato REDESIM (HU-022) — lógica REAL
 * atrás de contrato.
 *
 * ⚠️ A ESTRUTURA DO PAYLOAD É REFERÊNCIA, A VALIDAR COM A SEDUR (integrador
 * estadual REGIN/JUCEB). O manual nacional REDESIM (WS01/WS02/...) não é
 * público; os campos aqui derivam da Resolução CGSIM 61/2020 e da consulta
 * prévia de viabilidade. O TRANSPORTE real (webservice/fila do integrador)
 * é a HU-103 (Fase 13), que ajusta o parsing SEM retrabalho de domínio —
 * upsert por CNPJ, validação por item, sincronização de CNAEs e auditoria
 * já estão consolidados aqui.
 *
 * Campos de SOLICITAÇÃO de viabilidade (área ocupada, inscrição imobiliária,
 * forma de atuação) são IGNORADOS nesta fase — pertencem à Fase 8.
 *
 * Este serviço NUNCA cria vínculo usuário-empresa (company_user): o payload
 * REDESIM não traz o usuário do portal. A associação com o solicitante chega
 * com o transporte real (Fase 13).
 */
class RedesimImportService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Processa um arquivo JSON no formato REDESIM (objeto único ou array de
     * objetos) e faz upsert por CNPJ com relatório auditado.
     *
     * @return array{lidos: int, importados: int, atualizados: int, rejeitados: array<int, string>, avisos: array<int, string>}
     */
    public function import(string $jsonPath): array
    {
        if (! is_file($jsonPath) || ! is_readable($jsonPath)) {
            throw new RuntimeException("Arquivo REDESIM ilegível: {$jsonPath}");
        }

        $decoded = json_decode((string) file_get_contents($jsonPath), true, flags: JSON_THROW_ON_ERROR);

        // Aceita objeto único (detecta a chave 'protocolo') ou array de objetos.
        $items = isset($decoded['protocolo']) ? [$decoded] : $decoded;

        $relatorio = [
            'lidos' => 0,
            'importados' => 0,
            'atualizados' => 0,
            'rejeitados' => [],
            'avisos' => [],
        ];

        foreach ($items as $item) {
            $relatorio['lidos']++;
            $this->processItem($item, $relatorio);
        }

        $todosRejeitados = $relatorio['lidos'] > 0
            && count($relatorio['rejeitados']) === $relatorio['lidos'];

        $this->audit->log(
            'empresas',
            'importacao-redesim',
            'Importação de dados REDESIM processada',
            $relatorio,
            result: $todosRejeitados ? 'falha' : 'sucesso',
            rulesVersion: 'redesim-import-v1',
        );

        return $relatorio;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{lidos: int, importados: int, atualizados: int, rejeitados: array<int, string>, avisos: array<int, string>}  $relatorio
     */
    private function processItem(array $item, array &$relatorio): void
    {
        $protocolo = trim((string) ($item['protocolo'] ?? ''));
        $empresa = $item['empresa'] ?? [];
        $endereco = $item['endereco'] ?? [];
        $atividades = $item['atividades'] ?? [];
        $contato = $item['contato'] ?? [];

        $rotulo = $protocolo !== '' ? "protocolo {$protocolo}" : 'item sem protocolo';

        if ($protocolo === '') {
            $relatorio['rejeitados'][] = "{$rotulo}: protocolo obrigatório";

            return;
        }

        $razaoSocial = trim((string) ($empresa['razao_social'] ?? ''));
        if ($razaoSocial === '') {
            $relatorio['rejeitados'][] = "{$rotulo}: razão social obrigatória";

            return;
        }

        $cnpj = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($empresa['cnpj'] ?? '')));

        if (Validator::make(['cnpj' => $cnpj], ['cnpj' => [new ValidCnpj]])->fails()) {
            $relatorio['rejeitados'][] = "{$rotulo}: CNPJ inválido ({$cnpj})";

            return;
        }

        $principalDigits = preg_replace('/\D/', '', (string) ($atividades['principal'] ?? ''));
        $principal = Cnae::query()->where('code', $principalDigits)->first();

        if ($principal === null) {
            $relatorio['rejeitados'][] = "{$rotulo}: CNAE principal {$principalDigits} inexistente na tabela oficial";

            return;
        }

        if (! $principal->active) {
            $relatorio['avisos'][] = "{$rotulo}: CNAE principal {$principalDigits} está inativo na tabela oficial";
        }

        // Resolve secundários: inexistentes/duplicados do principal viram aviso e são ignorados.
        $secundariosIds = [];
        foreach ((array) ($atividades['secundarias'] ?? []) as $codigo) {
            $digits = preg_replace('/\D/', '', (string) $codigo);

            if ($digits === '' || $digits === $principalDigits) {
                continue;
            }

            $cnae = Cnae::query()->where('code', $digits)->first();

            if ($cnae === null) {
                $relatorio['avisos'][] = "{$rotulo}: CNAE secundário {$digits} inexistente — ignorado";

                continue;
            }

            if (! $cnae->active) {
                $relatorio['avisos'][] = "{$rotulo}: CNAE secundário {$digits} está inativo na tabela oficial";
            }

            $secundariosIds[$cnae->id] = ['is_primary' => false];
        }

        $dados = [
            'legal_name' => $razaoSocial,
            'trade_name' => $empresa['nome_fantasia'] ?? null,
            'legal_nature_code' => $empresa['natureza_juridica']['codigo'] ?? null,
            'legal_nature' => $empresa['natureza_juridica']['descricao'] ?? null,
            'size_code' => $empresa['porte']['codigo'] ?? null,
            'size' => $empresa['porte']['descricao'] ?? null,
            'street' => $endereco['logradouro'] ?? null,
            'number' => $endereco['numero'] ?? null,
            'complement' => ($endereco['complemento'] ?? '') !== '' ? $endereco['complemento'] : null,
            'neighborhood' => $endereco['bairro'] ?? null,
            'city' => $endereco['municipio'] ?? null,
            'state' => $endereco['uf'] ?? null,
            'zip_code' => preg_replace('/\D/', '', (string) ($endereco['cep'] ?? '')) ?: null,
            'email' => $contato['email'] ?? null,
            'phone' => preg_replace('/\D/', '', (string) ($contato['telefone'] ?? '')) ?: null,
            'redesim_protocol' => $protocolo,
            'redesim_synced_at' => now(),
        ];

        DB::transaction(function () use ($cnpj, $dados, $principal, $secundariosIds, &$relatorio) {
            $company = Company::query()->where('cnpj', $cnpj)->first();

            if ($company !== null) {
                // Upsert: NÃO toca 'source' (origem de criação imutável — Pitfall 8).
                $company->update($dados);
                $relatorio['atualizados']++;
            } else {
                $company = Company::create(array_merge($dados, [
                    'cnpj' => $cnpj,
                    'source' => CompanySource::Redesim,
                ]));
                $relatorio['importados']++;
            }

            // Conjunto exato de CNAEs (principal + secundários resolvidos).
            $company->cnaes()->sync([$principal->id => ['is_primary' => true]] + $secundariosIds);
        });
    }
}
