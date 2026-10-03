<?php

namespace App\Services\Regin;

use App\Rules\ValidCnpj;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Declaração plana que o POST /api_integracao/recebe passa a receber e o
 * Viabiliza transforma em processo. O envelope RUC da Junta continua válido
 * quando estas chaves não vêm.
 */
final class RecebimentoProcesso
{
    /**
     * @param  list<string>  $cnaesSecundarios
     * @param  list<array{cnae: string, pergunta: string, resposta: string}>  $perguntas
     */
    public function __construct(
        public string $protocolo,
        public ?string $razaoSocial,
        public ?string $cpfSolicitante,
        public ?string $nomeSolicitante,
        public ?string $emailSolicitante,
        public ?string $inscricaoImobiliaria,
        public ?string $codlog,
        public ?string $tipoLogradouro,
        public ?string $logradouro,
        public ?string $numero,
        public ?string $bairro,
        public ?string $cep,
        public ?string $pontoReferencia,
        public ?string $municipio,
        public ?string $uf,
        public ?float $area,
        public ?string $tipoComplemento,
        public ?string $descricaoComplemento,
        public ?string $categoriaImovel,
        public ?string $cnaePrincipal,
        public array $cnaesSecundarios,
        public array $perguntas,
        public ?string $tipoImovel,
        public ?string $porteEmpresa,
        public ?bool $atividadeEmAreaPublica,
        public ?bool $escritorioVirtual,
        public ?bool $atividadeAbrigadaEmEscritorioVirtual,
        public ?bool $prestaServicoDeEscritorioVirtual,
        public ?string $cnpjEscritorioVirtual,
        public ?string $servico,
        public ?Carbon $dataAbertura,
        public ?bool $acessosIndependentes,
        public ?array $poligonoDeclarado,
        public RecebimentoComplemento $complemento,
    ) {}

    /**
     * @param  array<string, mixed>  $envelope
     */
    public static function reconhece(array $envelope): bool
    {
        foreach ([
            'razaoSocial',
            'cpfSolicitante',
            'nomeSolicitante',
            'emailSolicitante',
            'inscricaoImobiliaria',
            'codlog',
            'tipoLogradouro',
            'logradouro',
            'numero',
            'bairro',
            'cep',
            'pontoReferencia',
            'municipio',
            'uf',
            'area',
            'tipoComplemento',
            'descricaoComplemento',
            'categoriaImovel',
            'cnaePrincipal',
            'cnaesSecundarios',
            'perguntas',
            'tipoImovel',
            'porteEmpresa',
            'atividadeEmAreaPublica',
            'escritorioVirtual',
            'atividadeAbrigadaEmEscritorioVirtual',
            'prestaServicoDeEscritorioVirtual',
            'cnpjEscritorioVirtual',
            'dataAbertura',
            'areaUtilizada',
            'acessosIndependentes',
            'poligonoDeclarado',
            'evento',
            'objetoSocial',
            'naturezaJuridica',
        ] as $chave) {
            if (array_key_exists($chave, $envelope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    public static function fromEnvelope(array $envelope): self
    {
        $abrigada = self::booleano($envelope['atividadeAbrigadaEmEscritorioVirtual'] ?? null);

        return new self(
            protocolo: trim((string) ($envelope['protocolo'] ?? '')),
            razaoSocial: self::texto($envelope['razaoSocial'] ?? null, 255),
            cpfSolicitante: self::digitos($envelope['cpfSolicitante'] ?? null, 11),
            nomeSolicitante: self::texto($envelope['nomeSolicitante'] ?? null, 255),
            emailSolicitante: self::email($envelope['emailSolicitante'] ?? null),
            inscricaoImobiliaria: self::texto($envelope['inscricaoImobiliaria'] ?? null, 32),
            codlog: self::texto($envelope['codlog'] ?? null, 32),
            tipoLogradouro: self::texto($envelope['tipoLogradouro'] ?? null, 40),
            logradouro: self::texto($envelope['logradouro'] ?? null, 255),
            numero: self::texto($envelope['numero'] ?? null, 20),
            bairro: self::texto($envelope['bairro'] ?? null, 255),
            cep: self::digitos($envelope['cep'] ?? null, 8),
            pontoReferencia: self::texto($envelope['pontoReferencia'] ?? null, 255),
            municipio: self::texto($envelope['municipio'] ?? null, 255),
            uf: self::uf($envelope['uf'] ?? null),
            area: self::area($envelope['areaUtilizada'] ?? $envelope['area'] ?? null),
            tipoComplemento: self::texto($envelope['tipoComplemento'] ?? null, 80),
            descricaoComplemento: self::texto($envelope['descricaoComplemento'] ?? null, 255),
            categoriaImovel: self::texto($envelope['categoriaImovel'] ?? null, 120),
            cnaePrincipal: self::cnae($envelope['cnaePrincipal'] ?? null),
            cnaesSecundarios: self::cnaes($envelope['cnaesSecundarios'] ?? null),
            perguntas: self::perguntas($envelope['perguntas'] ?? null),
            tipoImovel: self::texto($envelope['tipoImovel'] ?? null, 120),
            porteEmpresa: self::texto($envelope['porteEmpresa'] ?? null, 80),
            atividadeEmAreaPublica: self::booleano($envelope['atividadeEmAreaPublica'] ?? null),
            escritorioVirtual: self::booleano($envelope['escritorioVirtual'] ?? null),
            atividadeAbrigadaEmEscritorioVirtual: $abrigada,
            prestaServicoDeEscritorioVirtual: self::booleano($envelope['prestaServicoDeEscritorioVirtual'] ?? null),
            cnpjEscritorioVirtual: $abrigada === true ? self::cnpj($envelope['cnpjEscritorioVirtual'] ?? null) : null,
            servico: self::texto($envelope['servico'] ?? null, 255),
            dataAbertura: self::data($envelope['dataAbertura'] ?? null),
            acessosIndependentes: self::booleano($envelope['acessosIndependentes'] ?? null),
            poligonoDeclarado: self::poligono($envelope['poligonoDeclarado'] ?? null),
            complemento: RecebimentoComplemento::fromEnvelope($envelope),
        );
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function fromStored(array $dados): self
    {
        $perguntas = [];

        foreach (is_array($dados['perguntas'] ?? null) ? $dados['perguntas'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $cnae = self::cnae($item['cnae'] ?? null);
            $pergunta = self::texto($item['pergunta'] ?? null, 500);
            $resposta = self::texto($item['resposta'] ?? null, 500);

            if ($cnae === null || $pergunta === null || $resposta === null) {
                continue;
            }

            $perguntas[] = [
                'cnae' => $cnae,
                'pergunta' => $pergunta,
                'resposta' => $resposta,
            ];
        }

        $secundarios = [];

        foreach (is_array($dados['cnaes_secundarios'] ?? null) ? $dados['cnaes_secundarios'] : [] as $codigo) {
            $cnae = self::cnae($codigo);

            if ($cnae !== null) {
                $secundarios[] = $cnae;
            }
        }

        return new self(
            protocolo: trim((string) ($dados['protocolo'] ?? '')),
            razaoSocial: self::texto($dados['razao_social'] ?? null, 255),
            cpfSolicitante: self::digitos($dados['cpf_solicitante'] ?? null, 11),
            nomeSolicitante: self::texto($dados['nome_solicitante'] ?? null, 255),
            emailSolicitante: self::email($dados['email_solicitante'] ?? null),
            inscricaoImobiliaria: self::texto($dados['inscricao_imobiliaria'] ?? null, 32),
            codlog: self::texto($dados['codlog'] ?? null, 32),
            tipoLogradouro: self::texto($dados['tipo_logradouro'] ?? null, 40),
            logradouro: self::texto($dados['logradouro'] ?? null, 255),
            numero: self::texto($dados['numero'] ?? null, 20),
            bairro: self::texto($dados['bairro'] ?? null, 255),
            cep: self::digitos($dados['cep'] ?? null, 8),
            pontoReferencia: self::texto($dados['ponto_referencia'] ?? null, 255),
            municipio: self::texto($dados['municipio'] ?? null, 255),
            uf: self::uf($dados['uf'] ?? null),
            area: self::area($dados['area_utilizada'] ?? $dados['area'] ?? null),
            tipoComplemento: self::texto($dados['tipo_complemento'] ?? null, 80),
            descricaoComplemento: self::texto($dados['descricao_complemento'] ?? null, 255),
            categoriaImovel: self::texto($dados['categoria_imovel'] ?? null, 120),
            cnaePrincipal: self::cnae($dados['cnae_principal'] ?? null),
            cnaesSecundarios: array_values(array_unique($secundarios)),
            perguntas: $perguntas,
            tipoImovel: self::texto($dados['tipo_imovel'] ?? null, 120),
            porteEmpresa: self::texto($dados['porte_empresa'] ?? null, 80),
            atividadeEmAreaPublica: self::booleano($dados['atividade_em_area_publica'] ?? null),
            escritorioVirtual: self::booleano($dados['escritorio_virtual'] ?? null),
            atividadeAbrigadaEmEscritorioVirtual: self::booleano($dados['atividade_abrigada_em_escritorio_virtual'] ?? null),
            prestaServicoDeEscritorioVirtual: self::booleano($dados['presta_servico_de_escritorio_virtual'] ?? null),
            cnpjEscritorioVirtual: self::cnpj($dados['cnpj_escritorio_virtual'] ?? null),
            servico: self::texto($dados['servico'] ?? null, 255),
            dataAbertura: self::data(is_string($dados['data_abertura'] ?? null) ? $dados['data_abertura'] : null),
            acessosIndependentes: self::booleano($dados['acessos_independentes'] ?? null),
            poligonoDeclarado: self::poligono($dados['poligono_declarado'] ?? null),
            complemento: RecebimentoComplemento::fromStored($dados),
        );
    }

    public function podeProtocolar(): bool
    {
        return $this->faltamParaProtocolar() === [];
    }

    /**
     * Campos sem os quais a declaração não vira processo. A recusa (`0`) usa
     * os nomes do contrato, não os da tabela.
     *
     * @return list<string>
     */
    public function faltamParaProtocolar(): array
    {
        $faltam = [];

        if ($this->razaoSocial === null) {
            $faltam[] = 'razaoSocial';
        }

        if ($this->cnaePrincipal === null) {
            $faltam[] = 'cnaePrincipal';
        }

        if ($this->area === null) {
            $faltam[] = 'areaUtilizada';
        }

        if ($this->logradouro === null) {
            $faltam[] = 'logradouro';
        }

        if ($this->bairro === null) {
            $faltam[] = 'bairro';
        }

        if ($this->cep === null) {
            $faltam[] = 'cep';
        }

        return $faltam;
    }

    public function complemento(): ?string
    {
        $texto = trim(implode(' ', array_filter([
            $this->tipoComplemento,
            $this->descricaoComplemento,
        ])));

        return $texto === '' ? null : $texto;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'protocolo' => $this->protocolo,
            'razao_social' => $this->razaoSocial,
            'cpf_solicitante' => $this->cpfSolicitante,
            'nome_solicitante' => $this->nomeSolicitante,
            'email_solicitante' => $this->emailSolicitante,
            'inscricao_imobiliaria' => $this->inscricaoImobiliaria,
            'codlog' => $this->codlog,
            'tipo_logradouro' => $this->tipoLogradouro,
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'bairro' => $this->bairro,
            'cep' => $this->cep,
            'ponto_referencia' => $this->pontoReferencia,
            'municipio' => $this->municipio,
            'uf' => $this->uf,
            'area_utilizada' => $this->area,
            'tipo_complemento' => $this->tipoComplemento,
            'descricao_complemento' => $this->descricaoComplemento,
            'categoria_imovel' => $this->categoriaImovel,
            'cnae_principal' => $this->cnaePrincipal,
            'cnaes_secundarios' => $this->cnaesSecundarios,
            'perguntas' => $this->perguntas,
            'tipo_imovel' => $this->tipoImovel,
            'porte_empresa' => $this->porteEmpresa,
            'atividade_em_area_publica' => $this->simNao($this->atividadeEmAreaPublica),
            'escritorio_virtual' => $this->simNao($this->escritorioVirtual),
            'atividade_abrigada_em_escritorio_virtual' => $this->simNao($this->atividadeAbrigadaEmEscritorioVirtual),
            'presta_servico_de_escritorio_virtual' => $this->simNao($this->prestaServicoDeEscritorioVirtual),
            'cnpj_escritorio_virtual' => $this->cnpjEscritorioVirtual,
            'servico' => $this->servico,
            'data_abertura' => $this->dataAbertura?->toDateString(),
            'acessos_independentes' => self::simNao($this->acessosIndependentes),
            'poligono_declarado' => $this->poligonoDeclarado,
            ...$this->complemento->toArray(),
        ];
    }

    public static function booleano(mixed $valor): ?bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if (is_int($valor)) {
            return match ($valor) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        if (! is_string($valor)) {
            return null;
        }

        return match (mb_strtolower(trim($valor))) {
            'sim', 's', 'true', '1' => true,
            'não', 'nao', 'n', 'false', '0' => false,
            default => null,
        };
    }

    public static function simNao(?bool $valor): ?string
    {
        return match ($valor) {
            true => 'Sim',
            false => 'Não',
            null => null,
        };
    }

    public static function texto(mixed $valor, int $limite): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        return mb_substr($texto, 0, $limite);
    }

    private static function digitos(mixed $valor, int $tamanho): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';

        if ($digitos === '') {
            return null;
        }

        return mb_substr($digitos, 0, $tamanho);
    }

    private static function email(mixed $valor): ?string
    {
        $texto = self::texto($valor, 255);

        if ($texto === null || filter_var($texto, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $texto;
    }

    private static function uf(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        if (preg_match('/^[A-Za-z]{2}$/', $texto) !== 1) {
            return null;
        }

        return strtoupper($texto);
    }

    public static function area(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            $area = (float) $valor;

            return $area > 0 ? $area : null;
        }

        if (! is_string($valor)) {
            return null;
        }

        $normalizado = str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $valor) ?? '');

        if ($normalizado === '' || ! is_numeric($normalizado)) {
            return null;
        }

        $area = (float) $normalizado;

        return $area > 0 ? $area : null;
    }

    public static function cnae(mixed $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';

        if ($digitos === '') {
            return null;
        }

        return mb_substr($digitos, 0, 7);
    }

    /**
     * @return list<string>
     */
    private static function cnaes(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $codigos = [];

        foreach ($valor as $item) {
            $codigo = self::cnae(is_array($item) ? ($item['codigo'] ?? $item['cnae'] ?? null) : $item);

            if ($codigo !== null) {
                $codigos[] = $codigo;
            }
        }

        return array_values(array_unique($codigos));
    }

    /**
     * @return list<array{cnae: string, pergunta: string, resposta: string}>
     */
    private static function perguntas(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $lista = [];

        foreach ($valor as $item) {
            if (! is_array($item)) {
                continue;
            }

            $cnae = self::cnae($item['cnae'] ?? null);
            $pergunta = self::texto($item['pergunta'] ?? null, 500);
            $resposta = self::texto($item['resposta'] ?? null, 500);

            if ($cnae === null || $pergunta === null || $resposta === null) {
                continue;
            }

            $lista[] = [
                'cnae' => $cnae,
                'pergunta' => $pergunta,
                'resposta' => $resposta,
            ];
        }

        return $lista;
    }

    private static function cnpj(mixed $valor): ?string
    {
        $normalizado = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $valor))) ?? '';

        if ($normalizado === '') {
            return null;
        }

        $valido = Validator::make(
            ['cnpj' => $normalizado],
            ['cnpj' => [new ValidCnpj]],
        )->passes();

        return $valido ? $normalizado : null;
    }

    /**
     * Geometria de ÁREA em GeoJSON (EPSG:4326, ordem longitude/latitude).
     * Linha e ponto não são lote: não viram polígono do imóvel.
     *
     * @return array<string, mixed>|null
     */
    private static function poligono(mixed $valor): ?array
    {
        if (! is_array($valor)) {
            return null;
        }

        if (($valor['type'] ?? null) === 'Feature' && is_array($valor['geometry'] ?? null)) {
            $valor = $valor['geometry'];
        }

        $tipo = $valor['type'] ?? null;
        $coordenadas = $valor['coordinates'] ?? null;

        if (! in_array($tipo, ['Polygon', 'MultiPolygon'], true) || ! is_array($coordenadas) || $coordenadas === []) {
            return null;
        }

        return ['type' => $tipo, 'coordinates' => $coordenadas];
    }

    private static function data(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (Throwable) {
            return null;
        }
    }
}
