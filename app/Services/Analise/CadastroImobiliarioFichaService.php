<?php

namespace App\Services\Analise;

use App\Models\ViabilityRequest;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryUnavailableException;

class CadastroImobiliarioFichaService
{
    public function __construct(private PropertyRegistryLookup $lookup) {}

    /**
     * @return array{
     *   status: string,
     *   mensagem: string|null,
     *   inscricao: string|null,
     *   campos: array<string, string|null>,
     *   consultado_em: string|null,
     *   source: string|null
     * }
     */
    public function para(ViabilityRequest $request): array
    {
        $inscricao = trim((string) ($request->property_registration ?? ''));

        if ($inscricao === '') {
            return $this->payload(
                status: 'sem_inscricao',
                mensagem: 'Inscrição imobiliária não informada no processo.',
                inscricao: null,
            );
        }

        try {
            $result = $this->lookup->resolve($inscricao);
        } catch (PropertyRegistryUnavailableException) {
            return $this->payload(
                status: 'indisponivel',
                mensagem: 'Cadastro Imobiliário indisponível — pendente SEDUR/SEFAZ.',
                inscricao: $inscricao,
            );
        } catch (PropertyNotFoundException) {
            return $this->payload(
                status: 'nao_encontrado',
                mensagem: 'Inscrição não encontrada no Cadastro Imobiliário.',
                inscricao: $inscricao,
            );
        }

        $campos = $result->cadastro ?? PropertyCadastroCampos::vazios();

        // Se o provider devolveu coordenada sem campos tipados, ainda é "disponivel"
        // geograficamente, mas a certidão fica vazia (honesto — não inventa IPTU).
        return [
            'status' => 'disponivel',
            'mensagem' => null,
            'inscricao' => $inscricao,
            'campos' => $campos->toArray(),
            'consultado_em' => now()->toIso8601String(),
            'source' => $result->source,
        ];
    }

    /**
     * @return array{
     *   status: string,
     *   mensagem: string|null,
     *   inscricao: string|null,
     *   campos: array<string, string|null>,
     *   consultado_em: null,
     *   source: null
     * }
     */
    private function payload(string $status, string $mensagem, ?string $inscricao): array
    {
        return [
            'status' => $status,
            'mensagem' => $mensagem,
            'inscricao' => $inscricao,
            'campos' => PropertyCadastroCampos::vazios()->toArray(),
            'consultado_em' => null,
            'source' => null,
        ];
    }
}
