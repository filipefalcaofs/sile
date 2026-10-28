<?php

namespace Tests\Support\Sefaz;

use App\Services\Sefaz\SefazConsultaIndisponivelException;
use App\Services\Sefaz\SefazViabilidadeConsultaGateway;
use App\Services\Sefaz\ViabilidadeVinculada;

class FakeSefazViabilidadeConsultaGateway implements SefazViabilidadeConsultaGateway
{
    public int $consultas = 0;

    /** @var array<string, ViabilidadeVinculada> */
    private array $viabilidades = [];

    /** @var array<string, true> */
    private array $foraDoAr = [];

    /**
     * @param  list<string>  $cnaes
     */
    public function com(string $cnpj, string $numero, array $cnaes): self
    {
        $this->viabilidades[$this->digitos($cnpj)] = new ViabilidadeVinculada($numero, $cnaes);

        return $this;
    }

    public function foraDoAr(string $cnpj): self
    {
        $this->foraDoAr[$this->digitos($cnpj)] = true;

        return $this;
    }

    public function voltou(string $cnpj): self
    {
        unset($this->foraDoAr[$this->digitos($cnpj)]);

        return $this;
    }

    public function consultarPorCnpj(string $cnpj): ?ViabilidadeVinculada
    {
        $this->consultas++;
        $digitos = $this->digitos($cnpj);

        if (isset($this->foraDoAr[$digitos])) {
            throw new SefazConsultaIndisponivelException($digitos, 'Fake: SEFAZ fora do ar.');
        }

        return $this->viabilidades[$digitos] ?? null;
    }

    private function digitos(string $cnpj): string
    {
        return (string) preg_replace('/\D/', '', $cnpj);
    }
}
