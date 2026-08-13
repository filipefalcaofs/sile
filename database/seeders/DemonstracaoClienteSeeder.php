<?php

namespace Database\Seeders;

use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\User;
use App\Support\DemoMode;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Massa e credenciais para validação pela SEDUR no ambiente de demonstração
 * (Portainer). Idempotente: re-seed não duplica usuários nem perfis.
 *
 * Pré-requisito: SILE_DEMO_DATA=true no container de seed.
 * Senha padrão dos usuários de demo: SileDemo2026!
 */
class DemonstracaoClienteSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'SileDemo2026!';

    public const CLIENTE_GESTAO_EMAIL = 'validacao@sedur.salvador.ba.gov.br';

    public const CLIENTE_PORTAL_EMAIL = 'requerente@sedur.salvador.ba.gov.br';

    /** @var array<string, list<string>> */
    private const PERFIS_VALIDACAO = [
        'validacao-fase-02' => [
            'acessar-gestao',
            'consultar-cnaes',
        ],
        'validacao-fase-04' => [
            'acessar-gestao',
            'consultar-cnaes',
            'consultar-territorio',
        ],
        'validacao-fase-07' => [
            'acessar-gestao',
            'consultar-cnaes',
            'consultar-territorio',
            'consultar-louos',
        ],
        'validacao-fase-10' => [
            'acessar-gestao',
            'consultar-cnaes',
            'consultar-territorio',
            'consultar-louos',
            'consultar-solicitacoes',
            'analisar-processos',
            'emitir-tvl',
            'encaminhar-malha-fina',
        ],
        'validacao-fase-completa' => [
            'acessar-gestao',
            'consultar-cnaes',
            'consultar-territorio',
            'consultar-louos',
            'consultar-solicitacoes',
            'analisar-processos',
            'emitir-tvl',
            'encaminhar-malha-fina',
            'consultar-auditoria',
            'monitorar-lgpd',
            'gerenciar-alertas-abuso',
            'consultar-relatorios',
        ],
    ];

    /**
     * Seeders de catálogo seguros para rodar em produção (sem factories/Faker).
     */
    private const SEEDERS_HOMOLOGACAO = [
        RolesAndPermissionsSeeder::class,
        LegalTermSeeder::class,
        ParameterSeeder::class,
        CnaeSeeder::class,
        RiscoMunicipalSeeder::class,
        RiscoSanitarioSeeder::class,
        RiskTriggerSeeder::class,
        LouosQuadro7Seeder::class,
        LouosQuadro10Seeder::class,
        LouosQuadro11Seeder::class,
        ViabilityServiceTypeSeeder::class,
        DocumentRequirementSeeder::class,
        SectorSeeder::class,
        StandardTextSeeder::class,
        GeoLayerSeeder::class,
        HolidaySeeder::class,
    ];

    /**
     * Massa de demonstração em DIVERSAS SITUAÇÕES (dados fictícios, lógica REAL):
     * rascunho, protocolada, cancelada, contingência, fluxo expresso (deferida
     * com TVL sobre a zona fictícia), análise técnica (em análise, deferida com
     * malha fina, em pendência), comunicação multicanal, auditoria/explicabilidade
     * e massa de indicadores para os relatórios. Mesma ordem do DatabaseSeeder.
     * Todos idempotentes e com gates internos (DemoMode / driver pgsql).
     */
    private const SEEDERS_MASSA_DEMO = [
        DevAdminSeeder::class,
        CompanySeeder::class,
        SolicitacaoDevSeeder::class,
        ZonaFicticiaDevSeeder::class,
        ExpressoDevSeeder::class,
        AnaliseDevSeeder::class,
        ComunicacaoDevSeeder::class,
        AuditoriaDevSeeder::class,
        RelatoriosDevSeeder::class,
    ];

    public function run(): void
    {
        if (! DemoMode::enabled()) {
            $this->command?->warn('DemonstracaoClienteSeeder: ignorado — defina SILE_DEMO_DATA=true.');

            return;
        }

        $this->call(self::SEEDERS_HOMOLOGACAO);
        $this->seedPerfisValidacao();
        $this->seedUsuariosCliente();
        $this->call(self::SEEDERS_MASSA_DEMO);
        $this->rotacionarSenhasDev();
    }

    private function seedPerfisValidacao(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERFIS_VALIDACAO as $nome => $permissoes) {
            $role = Role::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
            $role->givePermissionTo($permissoes);
        }
    }

    private function seedUsuariosCliente(): void
    {
        $this->seedUsuario(
            self::CLIENTE_GESTAO_EMAIL,
            'Validação SEDUR',
            '70698543032',
            'validacao-fase-completa',
        );

        $this->seedUsuario(
            self::CLIENTE_PORTAL_EMAIL,
            'Requerente Demo SEDUR',
            '45317828791',
            'cidadao',
        );

        $this->command?->info('Gestão (validação): '.self::CLIENTE_GESTAO_EMAIL.' / '.self::DEMO_PASSWORD);
        $this->command?->info('Portal (requerente): '.self::CLIENTE_PORTAL_EMAIL.' / '.self::DEMO_PASSWORD);
        $this->command?->info('Perfil atual da validadora: validacao-fase-completa (ajuste em Gestão > Perfis).');
    }

    /**
     * O ambiente de demonstração é público: os usuários fictícios de dev
     * (@sile.dev, senha fraca "password") ganham a senha demo. Roda a cada
     * seed — recadastros manuais de senha nesses usuários não são esperados.
     */
    private function rotacionarSenhasDev(): void
    {
        User::query()
            ->where('email', 'like', '%@sile.dev')
            ->get()
            ->each(function (User $user): void {
                $user->forceFill(['password' => self::DEMO_PASSWORD])->save();
            });

        $this->command?->info('Usuários @sile.dev com senha demo: '.self::DEMO_PASSWORD);
    }

    private function seedUsuario(string $email, string $name, string $cpf, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'cpf' => $cpf,
                'phone' => null,
                'password' => self::DEMO_PASSWORD,
            ],
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if (! $user->hasRole($role)) {
            $user->syncRoles([$role]);
        }

        if ($term = LegalTerm::current('lgpd')) {
            LegalTermAcceptance::firstOrCreate(
                ['user_id' => $user->id, 'legal_term_id' => $term->id],
                ['ip_address' => '127.0.0.1', 'accepted_at' => now()],
            );
        }

        return $user;
    }
}
