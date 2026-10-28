<?php

namespace App\Services\Integracao;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Credencial da SEFAZ/SIGS na consulta de TVL: usuário e senha da configuração
 * trocados por um token Bearer com validade. O token é o instante de expiração
 * cifrado com a chave da aplicação — sem tabela, e adulterar ou expirar o
 * invalida.
 */
class TokenTvlService
{
    public function configurado(): bool
    {
        return (bool) config('viabiliza.integrations.sefaz_tvl.ativo')
            && $this->usuario() !== ''
            && $this->senha() !== '';
    }

    public function emitir(string $usuario, string $senha): ?string
    {
        $usuarioOk = hash_equals($this->usuario(), $usuario);
        $senhaOk = hash_equals($this->senha(), $senha);

        if (! $usuarioOk || ! $senhaOk) {
            return null;
        }

        return Crypt::encryptString('tvl|'.(now()->timestamp + $this->validadeSegundos()));
    }

    public function valido(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $conteudo = Crypt::decryptString($token);
        } catch (DecryptException) {
            return false;
        }

        if (! preg_match('/^tvl\|(\d+)$/', $conteudo, $partes)) {
            return false;
        }

        return (int) $partes[1] > now()->timestamp;
    }

    public function validadeSegundos(): int
    {
        return max(1, (int) config('viabiliza.integrations.sefaz_tvl.validade_minutos', 60)) * 60;
    }

    private function usuario(): string
    {
        return (string) config('viabiliza.integrations.sefaz_tvl.usuario');
    }

    private function senha(): string
    {
        return (string) config('viabiliza.integrations.sefaz_tvl.senha');
    }
}
