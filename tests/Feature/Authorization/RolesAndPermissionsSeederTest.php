<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_cria_papeis_e_permissoes_da_fase(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['cidadao', 'Analista Terceirizado', 'apoio', 'Coordenador', 'administrador'] as $role) {
            $this->assertSame($role, Role::findByName($role, 'web')->name);
        }

        $permissions = [
            'acessar-gestao',
            'consultar-acessos-de-qualquer-conta',
            'gerenciar-procuracoes-proprias',
            'manter-cnaes',
            'manter-usuarios',
            'manter-perfis',
            'manter-parametros',
            'consultar-cnaes',
            'consultar-louos',
            'manter-louos',
            'registrar-contingencia',
            'atendimento-presencial',
            'consultar-solicitacoes',
            'manter-tipos-servico',
            'manter-nomenclatura-servicos',
            'manter-requisitos-documentais',
        ];

        foreach ($permissions as $permission) {
            $this->assertSame($permission, Permission::findByName($permission, 'web')->name);
        }
    }

    public function test_atribuicoes_de_permissao_por_papel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $administrador = Role::findByName('administrador', 'web');
        $this->assertTrue($administrador->hasPermissionTo('acessar-gestao'));
        $this->assertTrue($administrador->hasPermissionTo('consultar-acessos-de-qualquer-conta'));

        foreach (['manter-cnaes', 'manter-usuarios', 'manter-perfis', 'manter-parametros', 'consultar-cnaes'] as $permission) {
            $this->assertTrue($administrador->hasPermissionTo($permission));
        }

        $analista = Role::findByName('Analista Terceirizado', 'web');
        $this->assertTrue($analista->hasPermissionTo('acessar-gestao'));
        $this->assertTrue($analista->hasPermissionTo('consultar-cnaes'));
        $this->assertFalse($analista->hasPermissionTo('manter-cnaes'));

        $gestor = Role::findByName('Coordenador', 'web');
        $this->assertTrue($gestor->hasPermissionTo('acessar-gestao'));
        $this->assertTrue($gestor->hasPermissionTo('consultar-cnaes'));
        $this->assertFalse($gestor->hasPermissionTo('manter-cnaes'));

        $cidadao = Role::findByName('cidadao', 'web');
        $this->assertTrue($cidadao->hasPermissionTo('gerenciar-procuracoes-proprias'));
        $this->assertFalse($cidadao->hasPermissionTo('acessar-gestao'));
        $this->assertFalse($cidadao->hasPermissionTo('consultar-cnaes'));
    }

    public function test_papeis_recebem_permissao_consultar_territorio(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(
            'consultar-territorio',
            Permission::findByName('consultar-territorio', 'web')->name,
        );

        foreach (['administrador', 'Analista Terceirizado', 'Coordenador'] as $role) {
            $this->assertTrue(
                Role::findByName($role, 'web')->hasPermissionTo('consultar-territorio'),
            );
        }

        $this->assertFalse(
            Role::findByName('cidadao', 'web')->hasPermissionTo('consultar-territorio'),
        );
    }

    /**
     * Manutenção do catálogo de camadas do GeoServer (manter-territorio):
     * espelha manter-louos/manter-cnaes — só o administrador.
     */
    public function test_apenas_administrador_recebe_manter_territorio(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(
            'manter-territorio',
            Permission::findByName('manter-territorio', 'web')->name,
        );

        $this->assertTrue(Role::findByName('administrador', 'web')->hasPermissionTo('manter-territorio'));

        foreach (['Analista Terceirizado', 'Coordenador', 'cidadao'] as $role) {
            $this->assertFalse(
                Role::findByName($role, 'web')->hasPermissionTo('manter-territorio'),
                "O papel {$role} não pode manter o catálogo de camadas do GeoServer.",
            );
        }
    }

    public function test_papeis_recebem_permissoes_de_louos(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['consultar-louos', 'manter-louos'] as $permission) {
            $this->assertSame(
                $permission,
                Permission::findByName($permission, 'web')->name,
            );
        }

        // Consulta dos Quadros vigentes: analista, gestor e administrador (espelha CNAEs/risco).
        foreach (['administrador', 'Analista Terceirizado', 'Coordenador'] as $role) {
            $this->assertTrue(
                Role::findByName($role, 'web')->hasPermissionTo('consultar-louos'),
            );
        }

        // Manutenção (publicação versionada dos Quadros): só o administrador.
        $this->assertTrue(Role::findByName('administrador', 'web')->hasPermissionTo('manter-louos'));
        $this->assertFalse(Role::findByName('Analista Terceirizado', 'web')->hasPermissionTo('manter-louos'));
        $this->assertFalse(Role::findByName('Coordenador', 'web')->hasPermissionTo('manter-louos'));
        $this->assertFalse(Role::findByName('cidadao', 'web')->hasPermissionTo('consultar-louos'));
    }

    public function test_papeis_recebem_permissoes_de_solicitacao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $solicitacao = [
            'registrar-contingencia',
            'atendimento-presencial',
            'consultar-solicitacoes',
            'manter-tipos-servico',
            'manter-nomenclatura-servicos',
            'manter-requisitos-documentais',
        ];

        foreach ($solicitacao as $permission) {
            $this->assertSame(
                $permission,
                Permission::findByName($permission, 'web')->name,
            );
        }

        // Administrador parametriza tudo; as chefias de análise não parametrizam os cadastros.
        foreach (['administrador'] as $role) {
            foreach ($solicitacao as $permission) {
                $this->assertTrue(
                    Role::findByName($role, 'web')->hasPermissionTo($permission),
                );
            }
        }

        // Analista só consulta os processos no backoffice (não registra contingência).
        $analista = Role::findByName('Analista Terceirizado', 'web');
        $this->assertTrue($analista->hasPermissionTo('consultar-solicitacoes'));
        $this->assertFalse($analista->hasPermissionTo('registrar-contingencia'));

        // Cidadão opera as próprias solicitações por policy, sem permissão nomeada.
        $this->assertFalse(
            Role::findByName('cidadao', 'web')->hasPermissionTo('consultar-solicitacoes'),
        );
    }

    public function test_estados_da_factory_atribuem_papel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(User::factory()->cidadao()->create()->hasRole('cidadao'));
        $this->assertTrue(User::factory()->analista()->create()->hasRole('Analista Terceirizado'));
        $this->assertTrue(User::factory()->apoio()->create()->hasRole('apoio'));
        $this->assertTrue(User::factory()->gestor()->create()->hasRole('Coordenador'));
        $this->assertTrue(User::factory()->administrador()->create()->hasRole('administrador'));
    }

    public function test_papel_apoio_tramita_sem_analisar(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // Apoio (tramitação): distribui os processos da caixa do setor para um
        // analista específico, mas NÃO analisa — sem analisar-processos, emitir
        // TVL, malha fina ou manutenção de setores.
        $apoio = Role::findByName('apoio', 'web');

        foreach ([
            'acessar-gestao',
            'consultar-cnaes',
            'consultar-territorio',
            'consultar-louos',
            'consultar-solicitacoes',
            'distribuir-processos',
        ] as $permission) {
            $this->assertTrue(
                $apoio->hasPermissionTo($permission),
                "O papel apoio deve ter {$permission}.",
            );
        }

        foreach ([
            'analisar-processos',
            'emitir-tvl',
            'encaminhar-malha-fina',
            'enviar-tvl-analise',
            'manter-setores',
        ] as $permission) {
            $this->assertFalse(
                $apoio->hasPermissionTo($permission),
                "O papel apoio não deve ter {$permission}.",
            );
        }
    }

    public function test_papeis_recebem_permissoes_da_analise_tecnica(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $novas = [
            'analisar-processos',
            'distribuir-processos',
            'emitir-tvl',
            'encaminhar-malha-fina',
            'manter-setores',
        ];

        foreach ($novas as $permission) {
            $this->assertSame(
                $permission,
                Permission::findByName($permission, 'web')->name,
            );
        }

        // Analista analisa, provoca malha fina e emite o TVL interno; não
        // distribui processos nem administra setores (atribuições do gestor).
        $analista = Role::findByName('Analista Terceirizado', 'web');
        foreach (['analisar-processos', 'encaminhar-malha-fina', 'emitir-tvl'] as $permission) {
            $this->assertTrue($analista->hasPermissionTo($permission));
        }
        $this->assertFalse($analista->hasPermissionTo('distribuir-processos'));
        $this->assertFalse($analista->hasPermissionTo('manter-setores'));

        // Gestor distribui e também analisa/emite TVL. Setores ficam só com o
        // administrador (relatório de usabilidade SEDUR 19/09, itens 03-04).
        $gestor = Role::findByName('Coordenador', 'web');
        foreach (['analisar-processos', 'distribuir-processos', 'emitir-tvl', 'encaminhar-malha-fina', 'analisar-malha-fina'] as $permission) {
            $this->assertTrue($gestor->hasPermissionTo($permission));
        }
        $this->assertFalse($gestor->hasPermissionTo('manter-setores'));

        // Administrador administra e distribui, mas não conclui processos.
        $administrador = Role::findByName('administrador', 'web');
        foreach (array_diff($novas, ['analisar-processos']) as $permission) {
            $this->assertTrue($administrador->hasPermissionTo($permission));
        }
        $this->assertFalse($administrador->hasPermissionTo('analisar-processos'));

        // Cidadão não recebe nenhuma permissão de backoffice da análise.
        $cidadao = Role::findByName('cidadao', 'web');
        foreach ($novas as $permission) {
            $this->assertFalse($cidadao->hasPermissionTo($permission));
        }
    }

    public function test_papeis_recebem_permissao_de_enviar_tvl_analise(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(
            'enviar-tvl-analise',
            Permission::findByName('enviar-tvl-analise', 'web')->name,
        );

        // Envio manual do processo à análise (tela T06): permissão DEDICADA
        // concedida a quem já opera a análise técnica — analista, gestor e
        // administrador.
        foreach (['Analista Terceirizado', 'Coordenador', 'administrador'] as $role) {
            $this->assertTrue(
                Role::findByName($role, 'web')->hasPermissionTo('enviar-tvl-analise'),
                "O papel {$role} deve poder enviar processos para análise.",
            );
        }

        // Cidadão não recebe nenhuma permissão de backoffice da análise.
        $this->assertFalse(
            Role::findByName('cidadao', 'web')->hasPermissionTo('enviar-tvl-analise'),
        );
    }

    public function test_papeis_recebem_permissoes_de_auditoria_e_compliance(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $novas = ['consultar-auditoria', 'monitorar-lgpd', 'gerenciar-alertas-abuso'];

        foreach ($novas as $permission) {
            $this->assertSame(
                $permission,
                Permission::findByName($permission, 'web')->name,
            );
        }

        // Consultar a trilha (HU-093..097) e gerenciar alertas de abuso
        // (HU-138): só o administrador — o grupo Auditoria sai do menu do
        // gestor (relatório de usabilidade SEDUR 19/09, item 06).
        $this->assertTrue(Role::findByName('administrador', 'web')->hasPermissionTo('consultar-auditoria'));
        $this->assertTrue(Role::findByName('administrador', 'web')->hasPermissionTo('gerenciar-alertas-abuso'));
        $this->assertFalse(Role::findByName('Coordenador', 'web')->hasPermissionTo('consultar-auditoria'));
        $this->assertFalse(Role::findByName('Coordenador', 'web')->hasPermissionTo('gerenciar-alertas-abuso'));

        // Painel LGPD (HU-098; DPO/admin): só o administrador.
        $this->assertTrue(Role::findByName('administrador', 'web')->hasPermissionTo('monitorar-lgpd'));
        $this->assertFalse(Role::findByName('Coordenador', 'web')->hasPermissionTo('monitorar-lgpd'));

        // Analista NÃO recebe nenhuma das três (default gestor/admin; sem papel
        // auditor dedicado — decisão CONTEXT).
        $analista = Role::findByName('Analista Terceirizado', 'web');
        foreach ($novas as $permission) {
            $this->assertFalse($analista->hasPermissionTo($permission));
        }

        // Cidadão tampouco recebe qualquer permissão de auditoria/compliance.
        $cidadao = Role::findByName('cidadao', 'web');
        foreach ($novas as $permission) {
            $this->assertFalse($cidadao->hasPermissionTo($permission));
        }
    }

    public function test_papeis_recebem_permissoes_de_relatorios(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $novas = ['consultar-relatorios', 'relatorios.produtividade.nominal'];

        foreach ($novas as $permission) {
            $this->assertSame(
                $permission,
                Permission::findByName($permission, 'web')->name,
            );
        }

        // Consultar relatórios e ver a produtividade nominal (HU-121): gestor e
        // administrador. A exportação NÃO tem permissão própria — herda a leitura
        // da tela (RN-007).
        foreach (['Coordenador', 'administrador'] as $role) {
            foreach ($novas as $permission) {
                $this->assertTrue(Role::findByName($role, 'web')->hasPermissionTo($permission));
            }
        }

        // Analista não consulta os relatórios gerenciais nem vê produtividade
        // nominal (default conservador RH/LGPD — vê só o próprio recorte).
        $analista = Role::findByName('Analista Terceirizado', 'web');
        foreach ($novas as $permission) {
            $this->assertFalse($analista->hasPermissionTo($permission));
        }

        // Cidadão tampouco recebe qualquer permissão de relatórios.
        $cidadao = Role::findByName('cidadao', 'web');
        foreach ($novas as $permission) {
            $this->assertFalse($cidadao->hasPermissionTo($permission));
        }
    }

    public function test_papeis_recebem_permissao_manter_tipos_imovel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(
            'manter-tipos-imovel',
            Permission::findByName('manter-tipos-imovel', 'web')->name,
        );

        // Administrador e gestor parametrizam os tipos de imóvel (espelha manter-tipos-servico).
        foreach (['administrador'] as $role) {
            $this->assertTrue(
                Role::findByName($role, 'web')->hasPermissionTo('manter-tipos-imovel'),
                "O papel {$role} deve ter manter-tipos-imovel.",
            );
        }

        // Analista e cidadão NÃO recebem a permissão.
        foreach (['Analista Terceirizado', 'cidadao'] as $role) {
            $this->assertFalse(
                Role::findByName($role, 'web')->hasPermissionTo('manter-tipos-imovel'),
                "O papel {$role} não deve ter manter-tipos-imovel.",
            );
        }
    }

    public function test_papeis_recebem_permissao_manter_gatilhos_risco(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(
            'manter-gatilhos-risco',
            Permission::findByName('manter-gatilhos-risco', 'web')->name,
        );

        // Administrador e gestor mantêm os gatilhos semi-expresso (espelha
        // manter-tipos-imovel — parametrização das regras do licenciamento).
        foreach (['administrador'] as $role) {
            $this->assertTrue(
                Role::findByName($role, 'web')->hasPermissionTo('manter-gatilhos-risco'),
                "O papel {$role} deve ter manter-gatilhos-risco.",
            );
        }

        // Analista e cidadão NÃO recebem a permissão.
        foreach (['Analista Terceirizado', 'cidadao'] as $role) {
            $this->assertFalse(
                Role::findByName($role, 'web')->hasPermissionTo('manter-gatilhos-risco'),
                "O papel {$role} não deve ter manter-gatilhos-risco.",
            );
        }
    }

    public function test_papel_administrador_recebe_permissao_de_config_ia(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(
            'manter-config-ia',
            Permission::findByName('manter-config-ia', 'web')->name,
        );

        // Configuração de provedores de IA (Fase 14 — HU-014 aplicada à IA):
        // só o administrador parametriza (credenciais criptografadas, egress
        // externo). Espelha manter-config-email.
        $this->assertTrue(Role::findByName('administrador', 'web')->hasPermissionTo('manter-config-ia'));

        foreach (['Analista Terceirizado', 'Coordenador', 'cidadao'] as $role) {
            $this->assertFalse(
                Role::findByName($role, 'web')->hasPermissionTo('manter-config-ia'),
                "O papel {$role} não pode manter a configuração de IA.",
            );
        }
    }

    public function test_perfis_de_analise_e_chefia_entram_na_lista_de_distribuicao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $perfis = ['Chefe de setor', 'Coordenador', 'Subcoordenador', 'Analista Terceirizado', 'Analista Servidor'];

        foreach ($perfis as $nome) {
            $this->assertTrue(
                Role::findByName($nome, 'web')->hasPermissionTo('analisar-processos'),
                "O perfil {$nome} precisa de analisar-processos para receber distribuição.",
            );

            $usuario = User::factory()->create();
            $usuario->assignRole($nome);

            $this->assertContains(
                $usuario->id,
                User::query()->permission('analisar-processos')->pluck('id')->all(),
                "Usuário do perfil {$nome} deve aparecer na lista de distribuição.",
            );
        }
    }

    public function test_migration_concede_analise_aos_perfis_que_ficaram_sem_permissao(): void
    {
        $perfis = ['Chefe de setor', 'Coordenador', 'Subcoordenador', 'Analista Terceirizado', 'Analista Servidor'];

        foreach ($perfis as $nome) {
            Role::create(['name' => $nome, 'guard_name' => 'web']);
        }

        (require database_path('migrations/2026_10_15_120000_concede_analise_aos_perfis_de_distribuicao.php'))->up();

        foreach ($perfis as $nome) {
            $usuario = User::factory()->create();
            $usuario->assignRole($nome);

            $this->assertContains($usuario->id, User::query()->permission('analisar-processos')->pluck('id')->all(), $nome);
        }

        $this->assertTrue(Role::findByName('Coordenador', 'web')->hasPermissionTo('distribuir-processos'));
        $this->assertFalse(Role::findByName('Analista Servidor', 'web')->hasPermissionTo('distribuir-processos'));
    }

    public function test_migration_cria_distribuir_qualquer_setor_para_administrador_e_apoio(): void
    {
        foreach (['administrador', 'apoio', 'Coordenador'] as $nome) {
            Role::create(['name' => $nome, 'guard_name' => 'web']);
        }

        $this->assertNull(Permission::query()->where('name', 'distribuir-qualquer-setor')->first());

        (require database_path('migrations/2026_10_15_130000_cria_permissao_distribuir_qualquer_setor.php'))->up();

        foreach (['administrador', 'apoio'] as $nome) {
            $this->assertTrue(Role::findByName($nome, 'web')->hasPermissionTo('distribuir-qualquer-setor'), $nome);
            $this->assertTrue(Role::findByName($nome, 'web')->hasPermissionTo('distribuir-processos'), $nome);
        }

        $this->assertFalse(Role::findByName('Coordenador', 'web')->hasPermissionTo('distribuir-qualquer-setor'));
    }

    public function test_seeder_cria_perfis_do_legado_sem_permissao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $perfis = [
            'Analista Vistoriador',
            'Atendimento Sedur',
            'Diretor',
            'Fiscal',
            'Gerente',
            'GR',
            'Padrão',
            'Secretário',
            'SEFAZ',
            'Servidor',
            'Vistoriador',
        ];

        foreach ($perfis as $nome) {
            $perfil = Role::findByName($nome, 'web');
            $this->assertSame($nome, $perfil->name);
            $this->assertCount(0, $perfil->permissions, "O perfil {$nome} nasce sem permissão.");
        }

        $this->assertSame(19, Role::query()->count());
    }

    public function test_seeder_preserva_permissao_configurada_em_perfil_do_legado(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Role::findByName('Fiscal', 'web')->givePermissionTo('acessar-gestao');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(Role::findByName('Fiscal', 'web')->hasPermissionTo('acessar-gestao'));
        $this->assertSame(19, Role::query()->count());
    }

    public function test_seeder_preserva_perfil_fora_do_seed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Role::create(['name' => 'validacao-fase-02', 'guard_name' => 'web']);
        $usuario = User::factory()->create();
        $usuario->assignRole('validacao-fase-02');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(Role::query()->where('name', 'validacao-fase-02')->exists());
        $this->assertTrue($usuario->fresh()->hasRole('validacao-fase-02'));
        $this->assertTrue(Role::query()->where('name', 'cidadao')->exists());
        $this->assertTrue(Role::query()->where('name', 'Chefe de setor')->exists());
        $this->assertSame(20, Role::query()->count());
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(19, Role::query()->count());
        $this->assertSame(42, Permission::query()->count());
    }

    public function test_seeder_aditivo_preserva_ajustes_feitos_pela_interface(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Role::findByName('Analista Terceirizado', 'web')->givePermissionTo(
            Permission::firstOrCreate(['name' => 'permissao-extra-ui', 'guard_name' => 'web']),
        );

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(Role::findByName('Analista Terceirizado', 'web')->hasPermissionTo('permissao-extra-ui'));
    }

    public function test_so_os_perfis_de_analise_concluem_processos(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['Analista Terceirizado', 'Coordenador', 'Subcoordenador', 'Chefe de setor'] as $nome) {
            $this->assertTrue(Role::findByName($nome, 'web')->hasPermissionTo('analisar-processos'), $nome);
        }

        foreach (['administrador', 'apoio', 'cidadao'] as $nome) {
            $this->assertFalse(Role::findByName($nome, 'web')->hasPermissionTo('analisar-processos'), $nome);
        }
    }

    public function test_chefias_recebem_extras_e_analista_terceirizado_nao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $extras = [
            'distribuir-processos',
            'analisar-malha-fina',
            'consultar-relatorios',
            'relatorios.produtividade.nominal',
            'forcar-transicao-analise',
            'receber-escalonamento-sla',
        ];

        foreach (['Coordenador', 'Subcoordenador', 'Chefe de setor'] as $nome) {
            foreach ($extras as $permissao) {
                $this->assertTrue(Role::findByName($nome, 'web')->hasPermissionTo($permissao), "{$nome} / {$permissao}");
            }
        }

        foreach ($extras as $permissao) {
            $this->assertFalse(Role::findByName('Analista Terceirizado', 'web')->hasPermissionTo($permissao), $permissao);
        }
    }

    public function test_analista_e_gestor_nao_existem_mais_e_protegidos_vem_marcados(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseMissing('roles', ['name' => 'analista']);
        $this->assertDatabaseMissing('roles', ['name' => 'gestor']);
        $this->assertDatabaseHas('roles', ['name' => 'cidadao', 'protegido' => true]);
        $this->assertDatabaseHas('roles', ['name' => 'administrador', 'protegido' => true]);
        $this->assertDatabaseHas('roles', ['name' => 'apoio', 'protegido' => false]);
    }

    public function test_reseed_nao_apaga_perfil_criado_pela_interface(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Role::create(['name' => 'perfil-da-tela', 'guard_name' => 'web']);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'perfil-da-tela']);
    }
}
