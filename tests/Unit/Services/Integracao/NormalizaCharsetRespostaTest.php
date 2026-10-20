<?php

namespace Tests\Unit\Services\Integracao;

use App\Services\Integracao\NormalizaCharsetResposta;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class NormalizaCharsetRespostaTest extends TestCase
{
    private const TIPO_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private function normalizar(string $tipo, string $corpo): ResponseInterface
    {
        return (new NormalizaCharsetResposta)(new Response(200, ['Content-Type' => $tipo], $corpo));
    }

    public function test_nao_toca_em_planilha_xlsx_mesmo_com_openxml_no_tipo(): void
    {
        $binario = "PK\x03\x04\x14\x00\x08\x08\x08\x00\x18\xa3\x46\x5d\xff\xfe";

        $resposta = $this->normalizar(self::TIPO_XLSX, $binario);

        $this->assertSame($binario, (string) $resposta->getBody());
        $this->assertSame(self::TIPO_XLSX, $resposta->getHeaderLine('Content-Type'));
    }

    public function test_nao_toca_em_documento_word_nem_powerpoint(): void
    {
        $binario = "PK\x03\x04\xe9\xff";

        foreach ([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ] as $tipo) {
            $this->assertSame($binario, (string) $this->normalizar($tipo, $binario)->getBody());
        }
    }

    public function test_converte_json_em_iso_8859_1_do_geoserver(): void
    {
        $iso = "{\"nome\":\"A\xe7\xe3o\"}";

        $resposta = $this->normalizar('application/json', $iso);

        $this->assertSame('{"nome":"Ação"}', (string) $resposta->getBody());
        $this->assertStringContainsString('charset=UTF-8', $resposta->getHeaderLine('Content-Type'));
    }

    public function test_converte_xml_e_texto_em_iso_8859_1(): void
    {
        $iso = "<a>Ca\xe7\xe3o</a>";

        foreach (['text/xml', 'application/xml', 'application/gml+xml', 'text/plain'] as $tipo) {
            $this->assertSame('<a>Cação</a>', (string) $this->normalizar($tipo, $iso)->getBody(), $tipo);
        }
    }

    public function test_corpo_ja_em_utf8_fica_intacto(): void
    {
        $corpo = '{"nome":"Ação"}';

        $this->assertSame($corpo, (string) $this->normalizar('application/json', $corpo)->getBody());
    }
}
