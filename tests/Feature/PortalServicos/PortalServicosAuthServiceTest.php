<?php

namespace Tests\Feature\PortalServicos;

use App\Enums\AuthType;
use App\Models\AccessLog;
use App\Models\Activity;
use App\Models\Parameter;
use App\Models\User;
use App\Services\PortalServicos\EntradaPortalServicosRecusadaException;
use App\Services\PortalServicos\PortalServicosAuthService;
use App\Services\PortalServicos\PortalServicosSessao;
use App\Services\Sedur\SedurContatoClient;
use App\Services\Sedur\SedurContatoIndisponivelException;
use App\Services\Sedur\SedurPessoaFisica;
use App\Support\EmailProvisorio;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class PortalServicosAuthServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CPF = '52998224725';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
    }

    private function sessao(?string $nome = 'Fulana de Tal', ?string $email = 'fulana@exemplo.com', ?string $nivel = null, string $cpf = self::CPF): PortalServicosSessao
    {
        return new PortalServicosSessao($cpf, $nome, $email, $nivel);
    }

    private function autenticar(PortalServicosSessao $sessao): User
    {
        return app(PortalServicosAuthService::class)->autenticar($sessao);
    }

    private function contatoDevolve(?string $nome): void
    {
        $this->mock(SedurContatoClient::class, function (MockInterface $mock) use ($nome) {
            $nome === null
                ? $mock->shouldReceive('consultarCpf')->andThrow(new SedurContatoIndisponivelException('fora'))
                : $mock->shouldReceive('consultarCpf')->andReturn(new SedurPessoaFisica(self::CPF, $nome));
        });
    }

    public function test_conta_existente_ativa_e_devolvida_sem_mudar_auth_type(): void
    {
        $ad = User::factory()->ad('servidor.sedur')->create(['cpf' => self::CPF]);

        $user = $this->autenticar($this->sessao());

        $this->assertTrue($user->is($ad));
        $this->assertSame(AuthType::Ad, $user->fresh()->auth_type);
        $this->assertSame(1, User::query()->count());
    }

    public function test_conta_com_email_pendente_igual_ao_da_sessao_fica_confirmada(): void
    {
        $regin = User::factory()->unverified()->create(['cpf' => self::CPF, 'email' => 'fulana@exemplo.com']);

        $user = $this->autenticar($this->sessao(email: 'fulana@exemplo.com'));

        $this->assertTrue($user->is($regin));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame(AuthType::Local, $user->fresh()->auth_type);
    }

    public function test_email_pendente_nao_provado_e_trocado_pelo_da_sessao_antes_de_confirmar(): void
    {
        User::factory()->unverified()->create(['cpf' => self::CPF, 'email' => 'digitado.errado@exemplo.com']);

        $user = $this->autenticar($this->sessao(email: 'fulana@exemplo.com'));

        $this->assertSame('fulana@exemplo.com', $user->fresh()->email);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_pendente_nao_provado_vira_provisorio_quando_o_da_sessao_e_de_outra_conta(): void
    {
        User::factory()->create(['email' => 'fulana@exemplo.com']);
        User::factory()->unverified()->create(['cpf' => self::CPF, 'email' => 'digitado.errado@exemplo.com']);

        $user = $this->autenticar($this->sessao(email: 'fulana@exemplo.com'));

        $this->assertSame(EmailProvisorio::para(self::CPF), $user->fresh()->email);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_provisorio_e_trocado_pelo_da_sessao(): void
    {
        User::factory()->unverified()->create(['cpf' => self::CPF, 'email' => EmailProvisorio::para(self::CPF)]);

        $user = $this->autenticar($this->sessao(email: 'fulana@exemplo.com'));

        $this->assertSame('fulana@exemplo.com', $user->fresh()->email);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertTrue(Activity::query()->where('event', 'email-confirmado-portal-servicos')->exists());
    }

    public function test_email_provisorio_fica_quando_o_da_sessao_e_de_outra_conta(): void
    {
        User::factory()->create(['email' => 'fulana@exemplo.com']);
        User::factory()->unverified()->create(['cpf' => self::CPF, 'email' => EmailProvisorio::para(self::CPF)]);

        $user = $this->autenticar($this->sessao(email: 'fulana@exemplo.com'));

        $this->assertSame(EmailProvisorio::para(self::CPF), $user->fresh()->email);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_primeiro_acesso_cria_cidadao_confirmado_com_auth_type_portal_servicos(): void
    {
        $user = $this->autenticar($this->sessao());

        $user = $user->fresh();
        $this->assertSame('Fulana de Tal', $user->name);
        $this->assertSame('fulana@exemplo.com', $user->email);
        $this->assertSame(self::CPF, $user->cpf);
        $this->assertSame(AuthType::PortalServicos, $user->auth_type);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->hasRole('cidadao'));
        $atividade = Activity::query()->where('event', 'cadastro-portal-servicos')->sole();
        $this->assertStringNotContainsString(self::CPF, (string) json_encode($atividade->properties));
    }

    public function test_primeiro_acesso_com_email_em_uso_nasce_com_email_provisorio(): void
    {
        User::factory()->create(['email' => 'fulana@exemplo.com']);

        $user = $this->autenticar($this->sessao());

        $this->assertSame(EmailProvisorio::para(self::CPF), $user->fresh()->email);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_primeiro_acesso_sem_email_nasce_com_email_provisorio(): void
    {
        $user = $this->autenticar($this->sessao(email: null));

        $this->assertSame(EmailProvisorio::para(self::CPF), $user->fresh()->email);
    }

    public function test_primeiro_acesso_sem_nome_usa_cadastro_da_sedur(): void
    {
        $this->contatoDevolve('Nome Do Cadastro');

        $user = $this->autenticar($this->sessao(nome: null));

        $this->assertSame('Nome Do Cadastro', $user->fresh()->name);
    }

    public function test_primeiro_acesso_sem_nome_e_cadastro_fora_usa_o_cpf_formatado(): void
    {
        $this->contatoDevolve(null);

        $user = $this->autenticar($this->sessao(nome: null));

        $this->assertSame('529.982.247-25', $user->fresh()->name);
    }

    public function test_conta_inativa_e_recusada(): void
    {
        User::factory()->inactive()->create(['cpf' => self::CPF]);

        try {
            $this->autenticar($this->sessao());
            $this->fail('Conta inativa não pode entrar.');
        } catch (EntradaPortalServicosRecusadaException $exception) {
            $this->assertSame('Sua conta está inativa. Procure a SEDUR.', $exception->mensagemUsuario);
        }

        $this->assertTrue(AccessLog::query()->where('event', 'inativada')->where('channel', 'portal')->exists());
    }

    public function test_nivel_abaixo_do_minimo_e_recusado(): void
    {
        Parameter::query()->where('key', 'security.govbr.minimum_level')->update(['value' => 'prata']);
        Cache::flush();

        $this->expectException(EntradaPortalServicosRecusadaException::class);

        $this->autenticar($this->sessao(nivel: 'bronze'));
    }

    public function test_sem_nivel_na_sessao_nao_bloqueia(): void
    {
        Parameter::query()->where('key', 'security.govbr.minimum_level')->update(['value' => 'ouro']);
        Cache::flush();

        $user = $this->autenticar($this->sessao(nivel: null));

        $this->assertSame(self::CPF, $user->cpf);
    }

    public function test_cpf_invalido_e_recusado(): void
    {
        $this->expectException(EntradaPortalServicosRecusadaException::class);

        $this->autenticar($this->sessao(cpf: '11111111111'));
    }

    public function test_corrida_no_primeiro_acesso_nao_duplica_conta(): void
    {
        $outraAba = null;

        User::creating(function (User $user) use (&$outraAba) {
            if ($outraAba === null && $user->cpf === self::CPF) {
                $outraAba = DB::table('users')->insertGetId([
                    'name' => 'Outra aba',
                    'email' => 'outra.aba@exemplo.com',
                    'cpf' => self::CPF,
                    'password' => 'x',
                    'auth_type' => AuthType::PortalServicos->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $user = $this->autenticar($this->sessao());

        $this->assertSame($outraAba, $user->id);
        $this->assertSame(1, User::query()->where('cpf', self::CPF)->count());
    }

    public function test_regin_continua_usando_o_mesmo_email_provisorio(): void
    {
        $this->assertSame(self::CPF.'@solicitante.viabiliza.local', EmailProvisorio::para(self::CPF));
        $this->assertTrue(EmailProvisorio::eh(self::CPF.'@solicitante.viabiliza.local'));
        $this->assertFalse(EmailProvisorio::eh('fulana@exemplo.com'));
        $this->assertFalse(EmailProvisorio::eh(null));
    }
}
