<?php

namespace Tests\Feature\Renovacao;

use App\Enums\AnalysisCategory;
use App\Enums\CompanyLinkRole;
use App\Enums\CompanySource;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cidadao;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ServiceDeclaration;
use App\Models\ServiceDeclarationAcceptance;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Cnpj\CnpjData;
use App\Services\Cnpj\CnpjLookup;
use App\Services\Cnpj\CnpjLookupException;
use App\Services\Legado\LegadoViabilidadeClient;
use App\Services\Legado\TvlLegadaDetalhe;
use App\Services\Renovacao\RenovacaoElegibilidade;
use App\Services\Renovacao\RenovacaoElegibilidadeService;
use App\Services\Renovacao\RenovacaoLegadaElegibilidadeService;
use App\Services\Renovacao\RenovacaoProtocoloService;
use App\Services\Renovacao\ResolvedorEnderecoImovel;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

class RenovacaoLegadaProtocoloTest extends TestCase
{
    use FakeCadastroImobiliario;
    use LazilyRefreshDatabase;

    private const CNPJ = '11222333000181';

    private const OUTRO_CNPJ = '99888777000166';

    private const CODIGO_CNAE = '4721102';

    private ServiceDeclaration $declaracao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Cnae::factory()->create(['code' => self::CODIGO_CNAE]);
        $tipo = ViabilityServiceType::factory()->create(['code' => 'renovacao-tvl']);
        $this->declaracao = ServiceDeclaration::factory()->create([
            'service_type_id' => $tipo->id,
            'version' => 1,
            'required' => true,
        ]);
        $this->app->instance(CnpjLookup::class, new class implements CnpjLookup
        {
            public function lookup(string $cnpj): CnpjData
            {
                throw new CnpjLookupException('fora do ar');
            }
        });
    }

    public function test_cria_o_processo_com_numero_legado_snapshot_empresa_e_atividades(): void
    {
        $empresa = Company::factory()->create(['cnpj' => self::CNPJ, 'email' => 'contato@padaria.com', 'phone' => '7133330000']);
        $ator = Cidadao::factory()->create();
        $this->cadastroImobiliario('RUA DA ENGOMADEIRA, 508', '508', '41200000');
        $tvl = $this->detalhe(['inscricao_imobiliaria' => '0123456789', 'complemento' => 'Sala 2']);

        $renovacao = $this->servico()->protocolarLegada(
            $tvl,
            self::CNPJ,
            $ator,
            ['email' => 'novo@example.com', 'phone' => '71999990000', 'mobile' => '71988880000'],
            [$this->declaracao->id],
        );

        $this->assertSame(1234567, $renovacao->renews_legacy_tvl_number);
        $this->assertNull($renovacao->renews_viability_request_id);
        $this->assertSame($empresa->id, $renovacao->company_id);
        $this->assertSame('RUA DA ENGOMADEIRA', $renovacao->address_street);
        $this->assertSame('508', $renovacao->address_number);
        $this->assertSame('41200000', $renovacao->address_zip);
        $this->assertSame('Sala 2', $renovacao->address_complement);
        $this->assertSame('0123456789', $renovacao->property_registration);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $renovacao->status);
        $this->assertSame(AnalysisCategory::SemiExpresso, $renovacao->analysis_category);
        $this->assertNotNull($renovacao->protocol_number);
        $this->assertSame([self::CODIGO_CNAE], $renovacao->cnaes()->pluck('cnaes.code')->all());
        $this->assertTrue((bool) $renovacao->cnaes()->first()->pivot->is_primary);
        $this->assertSame('contato@padaria.com', $empresa->fresh()->email);
        $this->assertSame('7133330000', $empresa->fresh()->phone);
        $this->assertTrue(
            ServiceDeclarationAcceptance::query()
                ->where('viability_request_id', $renovacao->id)
                ->exists(),
        );
    }

    public function test_contato_informado_preenche_so_o_que_a_empresa_nao_tem(): void
    {
        $empresa = Company::factory()->create(['cnpj' => self::CNPJ, 'email' => null, 'phone' => null]);

        $this->servico()->protocolarLegada(
            $this->detalhe(),
            self::CNPJ,
            Cidadao::factory()->create(),
            ['email' => 'novo@example.com', 'phone' => '71999990000'],
            [$this->declaracao->id],
        );

        $this->assertSame('novo@example.com', $empresa->fresh()->email);
        $this->assertSame('71999990000', $empresa->fresh()->phone);
    }

    public function test_snapshot_guarda_so_o_detalhe_usado(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);
        $tvl = $this->detalhe();

        $renovacao = $this->servico()->protocolarLegada(
            $tvl,
            self::CNPJ,
            Cidadao::factory()->create(),
            ['phone' => '71999990000'],
            [$this->declaracao->id],
        );

        $snapshot = $renovacao->fresh()->legacy_tvl_snapshot;

        $this->assertIsArray($snapshot);
        $this->assertSame(1234567, $snapshot['numero']);
        $this->assertSame('provisoria', $snapshot['tipo']);
        $this->assertSame('2026-12-31', $snapshot['validade']);
        $this->assertSame(self::CNPJ, $snapshot['titularDocumento']);
        $this->assertSame(self::CODIGO_CNAE, $snapshot['atividades'][0]['codigo']);
        $this->assertEqualsCanonicalizing(
            array_keys(get_object_vars($tvl)),
            array_keys($snapshot),
        );
        $this->assertStringNotContainsStringIgnoringCase('token', json_encode($snapshot));
        $this->assertStringNotContainsStringIgnoringCase('secret', json_encode($snapshot));
    }

    public function test_codigo_legado_formatado_e_normalizado_para_o_catalogo(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);
        $tvl = $this->detalhe(['atividades' => [['codigo' => '4721-1/02', 'descricao' => 'Padaria']]]);

        $renovacao = $this->servico()->protocolarLegada(
            $tvl,
            self::CNPJ,
            Cidadao::factory()->create(),
            ['phone' => '71999990000'],
            [$this->declaracao->id],
        );

        $this->assertSame([self::CODIGO_CNAE], $renovacao->cnaes()->pluck('cnaes.code')->all());
    }

    public function test_area_utilizada_ausente_no_legado_nao_impede(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);

        $renovacao = $this->servico()->protocolarLegada(
            $this->detalhe(),
            self::CNPJ,
            Cidadao::factory()->create(),
            ['phone' => '71999990000'],
            [$this->declaracao->id],
        );

        $this->assertNull($renovacao->used_area_m2);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $renovacao->status);
    }

    public function test_recusa_quando_falta_declaracao_obrigatoria(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);

        try {
            $this->servico()->protocolarLegada($this->detalhe(), self::CNPJ, Cidadao::factory()->create(), [], []);
            $this->fail('Esperava DomainException.');
        } catch (DomainException) {
            $this->assertSame(0, ViabilityRequest::query()->count());
        }
    }

    public function test_recusa_tvl_nao_elegivel_com_o_motivo(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Esta viabilidade já foi renovada.');

        $this->servico()->protocolarLegada(
            $this->detalhe(['renovada_depois' => true]),
            self::CNPJ,
            Cidadao::factory()->create(),
            ['phone' => '71999990000'],
            [$this->declaracao->id],
        );
    }

    public function test_recusa_tvl_de_outro_titular(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);

        $this->expectException(DomainException::class);

        $this->servico()->protocolarLegada(
            $this->detalhe(['titular' => ['documento' => self::OUTRO_CNPJ]]),
            self::CNPJ,
            Cidadao::factory()->create(),
            ['phone' => '71999990000'],
            [$this->declaracao->id],
        );
    }

    public function test_segundo_protocolo_da_mesma_tvl_e_recusado(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);
        $ator = Cidadao::factory()->create();
        $tvl = $this->detalhe();

        $this->servico()->protocolarLegada($tvl, self::CNPJ, $ator, ['phone' => '71999990000'], [$this->declaracao->id]);

        try {
            $this->servico()->protocolarLegada($tvl, self::CNPJ, $ator, ['phone' => '71999990000'], [$this->declaracao->id]);
            $this->fail('Esperava DomainException.');
        } catch (DomainException $exception) {
            $this->assertSame(RenovacaoElegibilidadeService::MOTIVO_EM_ANALISE, $exception->getMessage());
        }

        $this->assertSame(1, ViabilityRequest::query()->where('renews_legacy_tvl_number', 1234567)->count());
    }

    public function test_protocolo_simultaneo_nao_duplica(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);
        $lock = Cache::lock('renovacao-legada:1234567', 3600);
        $this->assertTrue($lock->get());

        try {
            $this->servico()->protocolarLegada(
                $this->detalhe(),
                self::CNPJ,
                Cidadao::factory()->create(),
                ['phone' => '71999990000'],
                [$this->declaracao->id],
            );
            $this->fail('Esperava DomainException.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'Já existe um protocolo em andamento para esta viabilidade. Aguarde alguns instantes.',
                $exception->getMessage(),
            );
        } finally {
            $lock->release();
        }

        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_reavaliacao_dentro_do_lock_recusa_renovacao_aberta_entre_a_checagem_previa_e_a_transacao(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);

        $this->partialMock(RenovacaoLegadaElegibilidadeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('avaliar')->once()->andReturnUsing(function (): RenovacaoElegibilidade {
                $concorrente = ViabilityRequest::factory()->create(['renews_legacy_tvl_number' => 1234567]);
                $concorrente->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

                return new RenovacaoElegibilidade(true, null);
            });
        });

        try {
            $this->servico()->protocolarLegada(
                $this->detalhe(),
                self::CNPJ,
                Cidadao::factory()->create(),
                ['phone' => '71999990000'],
                [$this->declaracao->id],
            );
            $this->fail('Esperava DomainException.');
        } catch (DomainException $exception) {
            $this->assertSame(RenovacaoElegibilidadeService::MOTIVO_EM_ANALISE, $exception->getMessage());
        }

        $this->assertSame(1, ViabilityRequest::query()->where('renews_legacy_tvl_number', 1234567)->count());
        $this->assertSame(0, ViabilityRequest::query()->whereNotNull('protocol_number')->count());
        $this->assertSame(0, ServiceDeclarationAcceptance::query()->count());
    }

    public function test_store_com_conferencia_cadastral_indisponivel_volta_ao_indice(): void
    {
        [$cidadao] = $this->cidadaoComEmpresa();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(['titular' => ['documento' => null]]));
        });

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', $this->corpo())
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', 'Não foi possível conferir o CNPJ agora. Tente novamente em instantes.');

        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_lock_e_liberado_apos_o_protocolo(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);

        $this->servico()->protocolarLegada(
            $this->detalhe(),
            self::CNPJ,
            Cidadao::factory()->create(),
            ['phone' => '71999990000'],
            [$this->declaracao->id],
        );

        $lock = Cache::lock('renovacao-legada:1234567', 60);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_store_cria_exatamente_um_processo_e_redireciona(): void
    {
        [$cidadao, $empresa] = $this->cidadaoComEmpresa();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)->andReturn($this->detalhe());
        });

        $resposta = $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', $this->corpo());

        $renovacao = ViabilityRequest::query()->where('renews_legacy_tvl_number', 1234567)->sole();
        $resposta->assertRedirect(route('portal.solicitacoes.show', $renovacao));
        $this->assertSame($empresa->id, $renovacao->company_id);
        $this->assertSame($cidadao->id, $renovacao->requester_cidadao_id);
    }

    public function test_endereco_do_processo_vem_do_cadastro_pela_inscricao(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);
        $chamadas = $this->cadastroImobiliario('Rua do Cadastro, 77 — Loja', '77', '40000111');
        $tvl = $this->detalhe([
            'inscricao_imobiliaria' => '051.480-81',
            'complemento' => 'Sala 2',
            'endereco' => ['logradouro' => '508', 'numero' => 'RUA DA ENGOMADEIRA', 'bairro' => 'CEP TROCADO', 'cep' => '99999999'],
        ]);

        $renovacao = $this->servico()->protocolarLegada($tvl, self::CNPJ, Cidadao::factory()->create(), ['phone' => '71999990000'], [$this->declaracao->id]);

        $this->assertSame('Rua do Cadastro', $renovacao->address_street);
        $this->assertSame('77', $renovacao->address_number);
        $this->assertSame('40000111', $renovacao->address_zip);
        $this->assertNull($renovacao->address_neighborhood);
        $this->assertSame('Sala 2', $renovacao->address_complement);
        $this->assertSame('051.480-81', $renovacao->property_registration);
        $this->assertSame(['05148081', '05148081'], $chamadas->getArrayCopy(), 'Uma consulta do endereço e outra do território, que é etapa à parte.');
    }

    public function test_tvl_sem_inscricao_mantem_o_endereco_do_legado(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);
        $chamadas = $this->cadastroImobiliario('Rua do Cadastro', '77', '40000111');

        $renovacao = $this->servico()->protocolarLegada($this->detalhe(), self::CNPJ, Cidadao::factory()->create(), ['phone' => '71999990000'], [$this->declaracao->id]);

        $this->assertSame('RUA DA ENGOMADEIRA', $renovacao->address_street);
        $this->assertSame('508', $renovacao->address_number);
        $this->assertSame('PITUBA', $renovacao->address_neighborhood);
        $this->assertSame('41200000', $renovacao->address_zip);
        $this->assertCount(0, $chamadas);
    }

    public function test_cadastro_fora_do_ar_nao_cria_o_processo(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);
        $this->cadastroImobiliario(falha: 'indisponivel');

        try {
            $this->servico()->protocolarLegada($this->detalhe(['inscricao_imobiliaria' => '0123456789']), self::CNPJ, Cidadao::factory()->create(), ['phone' => '71999990000'], [$this->declaracao->id]);
            $this->fail('Esperava DomainException.');
        } catch (DomainException $exception) {
            $this->assertSame(ResolvedorEnderecoImovel::M_INDISPONIVEL, $exception->getMessage());
        }

        $this->assertSame(0, ViabilityRequest::query()->count());
        $this->assertSame(0, ServiceDeclarationAcceptance::query()->count());
    }

    public function test_inscricao_nao_encontrada_nao_cria_o_processo(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);
        $this->cadastroImobiliario(falha: 'nao_encontrada');

        try {
            $this->servico()->protocolarLegada($this->detalhe(['inscricao_imobiliaria' => '0123456789']), self::CNPJ, Cidadao::factory()->create(), ['phone' => '71999990000'], [$this->declaracao->id]);
            $this->fail('Esperava DomainException.');
        } catch (DomainException $exception) {
            $this->assertSame(ResolvedorEnderecoImovel::M_NAO_ENCONTRADA, $exception->getMessage());
        }

        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_cadastro_sem_dados_de_endereco_nao_cria_o_processo(): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);
        $this->cadastroImobiliario(semCadastro: true);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(ResolvedorEnderecoImovel::M_NAO_ENCONTRADA);

        $this->servico()->protocolarLegada($this->detalhe(['inscricao_imobiliaria' => '0123456789']), self::CNPJ, Cidadao::factory()->create(), ['phone' => '71999990000'], [$this->declaracao->id]);
    }

    public function test_store_sem_cnpj_gravado_consulta_o_cadastro_uma_vez_e_grava_o_endereco_do_imovel(): void
    {
        [$cidadao] = $this->cidadaoComEmpresa();
        $this->app->instance(CnpjLookup::class, new class implements CnpjLookup
        {
            public function lookup(string $cnpj): CnpjData
            {
                return new CnpjData($cnpj, 'PADARIA ESTRELA LTDA', null, null, null, null, null, null, [], null, null, null, null, null, null, '40000111', null, null, null);
            }
        });
        $chamadas = $this->cadastroImobiliario('Rua do Cadastro', '77', '40000111');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(['titular' => ['documento' => null], 'inscricao_imobiliaria' => '0123456789']));
        });

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', $this->corpo())
            ->assertSessionMissing('error');

        $renovacao = ViabilityRequest::query()->sole();
        $this->assertSame('Rua do Cadastro', $renovacao->address_street);
        $this->assertSame('40000111', $renovacao->address_zip);
        $this->assertSame(
            ['0123456789', '0123456789'],
            $chamadas->getArrayCopy(),
            'Titularidade e endereço compartilham uma consulta; a outra é a do território, etapa à parte.',
        );
    }

    public function test_store_com_cadastro_fora_do_ar_volta_com_a_mensagem_sem_criar(): void
    {
        [$cidadao] = $this->cidadaoComEmpresa();
        $this->cadastroImobiliario(falha: 'indisponivel');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(['inscricao_imobiliaria' => '0123456789']));
        });

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', $this->corpo())
            ->assertSessionHas('error', ResolvedorEnderecoImovel::M_INDISPONIVEL);

        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_resolucao_do_endereco_acontece_antes_do_lock_com_cadastro_fora_do_ar(): void
    {
        $this->assertFalhaDoCadastroAntesDoLock('indisponivel', ResolvedorEnderecoImovel::M_INDISPONIVEL);
    }

    public function test_resolucao_do_endereco_acontece_antes_do_lock_com_inscricao_nao_encontrada(): void
    {
        $this->assertFalhaDoCadastroAntesDoLock('nao_encontrada', ResolvedorEnderecoImovel::M_NAO_ENCONTRADA);
    }

    private function assertFalhaDoCadastroAntesDoLock(string $falha, string $mensagem): void
    {
        Company::factory()->create(['cnpj' => self::CNPJ]);
        $this->cadastroImobiliario(falha: $falha);
        $lock = Cache::lock('renovacao-legada:1234567', 3600);
        $this->assertTrue($lock->get());

        try {
            $this->servico()->protocolarLegada($this->detalhe(['inscricao_imobiliaria' => '0123456789']), self::CNPJ, Cidadao::factory()->create(), ['phone' => '71999990000'], [$this->declaracao->id]);
            $this->fail('Esperava DomainException.');
        } catch (DomainException $exception) {
            $this->assertSame($mensagem, $exception->getMessage());
        } finally {
            $lock->release();
        }

        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_store_ignora_dados_postados_alem_de_contato_e_declaracoes(): void
    {
        [$cidadao] = $this->cidadaoComEmpresa();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)->andReturn($this->detalhe());
        });

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', $this->corpo() + [
                'address_street' => 'RUA FORJADA',
                'atividades' => ['9999999'],
                'legacy_tvl_snapshot' => ['numero' => 1],
            ]);

        $renovacao = ViabilityRequest::query()->sole();
        $this->assertSame('RUA DA ENGOMADEIRA', $renovacao->address_street);
        $this->assertSame(1234567, $renovacao->legacy_tvl_snapshot['numero']);
    }

    public function test_store_de_tvl_de_outro_titular_usa_o_redirect_generico_sem_criar(): void
    {
        [$cidadao] = $this->cidadaoComEmpresa();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(['titular' => ['documento' => self::OUTRO_CNPJ]]));
            $client->shouldReceive('tvlPorNumero')->with(7654321)->andReturn(null);
        });
        $generica = 'Esta viabilidade não pôde ser carregada para esta empresa.';

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', $this->corpo())
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', $generica);

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/7654321', $this->corpo())
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', $generica);

        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_store_de_empresa_nao_vinculada_protocola_na_empresa_do_cnpj(): void
    {
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $alheia = Company::factory()->create(['cnpj' => self::CNPJ]);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)->andReturn($this->detalhe());
        });

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', $this->corpo())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $renovacao = ViabilityRequest::query()->where('renews_legacy_tvl_number', 1234567)->sole();
        $this->assertSame($alheia->id, $renovacao->company_id);
        $this->assertSame($cidadao->id, $renovacao->requester_cidadao_id);
    }

    public function test_store_com_cnpj_da_rota_diferente_do_gravado_na_tvl_usa_o_redirect_generico_sem_criar(): void
    {
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)->andReturn($this->detalhe());
        });

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::OUTRO_CNPJ.'/1234567', $this->corpo())
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', 'Esta viabilidade não pôde ser carregada para esta empresa.');

        $this->assertSame(0, ViabilityRequest::query()->count());
        $this->assertSame(0, Company::query()->where('cnpj', self::OUTRO_CNPJ)->count());
    }

    public function test_store_cria_a_empresa_do_cnpj_quando_nao_existe(): void
    {
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)->andReturn($this->detalhe());
        });

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', $this->corpo())
            ->assertSessionHas('status');

        $empresa = Company::query()->where('cnpj', self::CNPJ)->sole();
        $this->assertSame(CompanySource::Legado, $empresa->source);
        $this->assertSame('PADARIA ESTRELA LTDA', $empresa->legal_name);
        $this->assertSame($empresa->id, ViabilityRequest::query()->sole()->company_id);
    }

    public function test_store_inelegivel_volta_com_o_motivo_sem_criar(): void
    {
        [$cidadao] = $this->cidadaoComEmpresa();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(['renovada_depois' => true]));
        });

        $this->actingAs($cidadao)
            ->from('/portal/renovacao/legado/'.self::CNPJ.'/1234567')
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', $this->corpo())
            ->assertRedirect('/portal/renovacao/legado/'.self::CNPJ.'/1234567')
            ->assertSessionHas('error', 'Esta viabilidade já foi renovada.');

        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_store_valida_o_contato(): void
    {
        [$cidadao] = $this->cidadaoComEmpresa();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldNotReceive('tvlPorNumero');
        });

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', ['declaration_ids' => []])
            ->assertSessionHasErrors(['phone']);

        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_post_duplo_cria_apenas_um_processo(): void
    {
        [$cidadao] = $this->cidadaoComEmpresa();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)->andReturn($this->detalhe());
        });

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', $this->corpo())
            ->assertRedirect();

        $this->actingAs($cidadao)
            ->post('/portal/renovacao/legado/'.self::CNPJ.'/1234567', $this->corpo())
            ->assertSessionHas('error', RenovacaoElegibilidadeService::MOTIVO_EM_ANALISE);

        $this->assertSame(1, ViabilityRequest::query()->where('renews_legacy_tvl_number', 1234567)->count());
    }

    private function servico(): RenovacaoProtocoloService
    {
        return app(RenovacaoProtocoloService::class);
    }

    /**
     * @return array{0: Cidadao, 1: Company}
     */
    private function cidadaoComEmpresa(): array
    {
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $empresa = Company::factory()->create(['cnpj' => self::CNPJ]);
        $empresa->links()->create([
            'cidadao_id' => $cidadao->id,
            'role' => CompanyLinkRole::Responsavel,
            'started_at' => now(),
        ]);

        return [$cidadao, $empresa];
    }

    /**
     * @return array<string, mixed>
     */
    private function corpo(): array
    {
        return [
            'email' => 'novo@example.com',
            'phone' => '71999990000',
            'declaration_ids' => [$this->declaracao->id],
        ];
    }

    private function mockClient(callable $expectativas): void
    {
        $this->mock(LegadoViabilidadeClient::class, $expectativas);
    }

    /**
     * @param  array<string, mixed>  $sobrescritas
     */
    private function detalhe(array $sobrescritas = []): TvlLegadaDetalhe
    {
        return TvlLegadaDetalhe::fromArray(array_merge([
            'numero' => 1234567,
            'tipo' => 'provisoria',
            'deferida' => true,
            'ativa' => true,
            'validade' => '2026-12-31',
            'situacao' => 'vigente',
            'dias_para_vencer' => 73,
            'renovada_depois' => false,
            'titular' => ['documento' => self::CNPJ],
            'razao_social' => 'PADARIA ESTRELA LTDA',
            'endereco' => ['logradouro' => 'RUA DA ENGOMADEIRA', 'numero' => '508', 'bairro' => 'PITUBA', 'cep' => '41200000'],
            'atividades' => [['codigo' => self::CODIGO_CNAE, 'descricao' => 'Padaria']],
        ], $sobrescritas));
    }
}
