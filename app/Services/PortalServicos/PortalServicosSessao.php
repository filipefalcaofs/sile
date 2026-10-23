<?php

namespace App\Services\PortalServicos;

/**
 * Dados da sessão do cidadão no Portal de Serviços. Só o CPF é garantido; o
 * formato completo do buscar-sessao ainda não foi confirmado com a SEDUR, por
 * isso a leitura aceita nomes alternativos e trata o resto como opcional.
 */
final readonly class PortalServicosSessao
{
    private const NIVEIS = [
        'bronze' => 'bronze',
        'prata' => 'prata',
        'silver' => 'prata',
        'ouro' => 'ouro',
        'gold' => 'ouro',
    ];

    public function __construct(
        public string $cpf,
        public ?string $nome,
        public ?string $email,
        public ?string $nivel,
    ) {}

    /**
     * @param  array<string, mixed>  $corpo
     *
     * @throws SessaoPortalServicosInvalidaException
     */
    public static function deResposta(array $corpo): self
    {
        $bruto = self::primeiro($corpo, ['cpf', 'documento', 'cpfCnpj', 'numeroDocumento']);
        // CPF vindo como número perde o zero à esquerda no JSON.
        $cpf = is_int($bruto) ? str_pad((string) $bruto, 11, '0', STR_PAD_LEFT) : preg_replace('/\D/', '', (string) $bruto);

        if ($cpf === null || strlen($cpf) !== 11) {
            throw new SessaoPortalServicosInvalidaException('Sessão do Portal de Serviços sem CPF de 11 dígitos.');
        }

        $nivel = strtolower(trim((string) self::primeiro($corpo, ['nivelConfiabilidade', 'nivel', 'reliabilityLevel'])));

        return new self(
            cpf: $cpf,
            nome: self::texto(self::primeiro($corpo, ['nome', 'name', 'nomeCompleto'])),
            email: self::email(self::primeiro($corpo, ['email', 'e-mail', 'mail'])),
            nivel: self::NIVEIS[$nivel] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $corpo
     * @param  list<string>  $chaves
     */
    private static function primeiro(array $corpo, array $chaves): mixed
    {
        foreach ($chaves as $chave) {
            if (isset($corpo[$chave]) && is_scalar($corpo[$chave]) && trim((string) $corpo[$chave]) !== '') {
                return $corpo[$chave];
            }
        }

        return null;
    }

    private static function texto(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private static function email(mixed $valor): ?string
    {
        $email = strtolower(trim((string) $valor));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
