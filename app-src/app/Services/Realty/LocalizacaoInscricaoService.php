<?php

namespace App\Services\Realty;

/**
 * Consulta a localização da inscrição no Cadastro Imobiliário uma vez por
 * execução (requisição ou job): protocolo, CODLOG tributário, regra territorial
 * e a checagem de divergência reaproveitam o mesmo resultado.
 */
class LocalizacaoInscricaoService
{
    /** @var array<string, LocalizacaoInscricao> */
    private array $memo = [];

    public function __construct(private PropertyRegistryLookup $cadastro) {}

    public function localizar(?string $inscricao): LocalizacaoInscricao
    {
        $digitos = preg_replace('/\D/', '', (string) $inscricao) ?? '';

        if ($digitos === '') {
            return new LocalizacaoInscricao(LocalizacaoInscricao::SEM_INSCRICAO);
        }

        return $this->memo[$digitos] ??= $this->consultar($digitos);
    }

    private function consultar(string $digitos): LocalizacaoInscricao
    {
        try {
            $resultado = $this->cadastro->resolve($digitos);
        } catch (PropertyNotFoundException) {
            return new LocalizacaoInscricao(LocalizacaoInscricao::NAO_ENCONTRADA);
        } catch (PropertyRegistryUnavailableException) {
            return new LocalizacaoInscricao(LocalizacaoInscricao::INDISPONIVEL);
        }

        $codigo = $resultado->raw['CodigoLogradouroTributario'] ?? null;
        $codLog = is_string($codigo) || is_numeric($codigo)
            ? ltrim(trim((string) $codigo), '0')
            : '';
        $nome = $resultado->raw['LogradouroTributario'] ?? $resultado->cadastro?->logradouro_tributario;
        $nome = is_string($nome) ? trim($nome) : '';

        $temPonto = $resultado->latitude !== null && $resultado->longitude !== null;

        return new LocalizacaoInscricao(
            status: $temPonto ? LocalizacaoInscricao::LOCALIZADA : LocalizacaoInscricao::SEM_COORDENADA,
            lat: $temPonto ? $resultado->latitude : null,
            lng: $temPonto ? $resultado->longitude : null,
            codLogTributario: $codLog !== '' ? $codLog : null,
            logradouroTributario: $nome !== '' ? $nome : null,
        );
    }
}
