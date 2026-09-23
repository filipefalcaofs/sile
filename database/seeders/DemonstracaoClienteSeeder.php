<?php

namespace Database\Seeders;

use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\Sector;
use App\Models\User;
use App\Support\DemoMode;
use Illuminate\Database\Seeder;

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

    /**
     * Usuários e empresas de demonstração. Processos NÃO entram aqui: o
     * container sile-seed roda a cada redeploy do Portainer e replantava a
     * massa (Solicitacao/Expresso/Analise/…) depois de o analista apagar.
     */
    private const SEEDERS_USUARIOS_DEMO = [
        DevAdminSeeder::class,
        CompanySeeder::class,
    ];

    public function run(): void
    {
        if (! DemoMode::enabled()) {
            $this->command?->warn('DemonstracaoClienteSeeder: ignorado — defina SILE_DEMO_DATA=true.');

            return;
        }

        $this->call(CatalogoOficialSeeder::class);
        $this->seedUsuariosCliente();
        $this->call(self::SEEDERS_USUARIOS_DEMO);
        $this->rotacionarSenhasDev();
        $this->vincularGestaoAoSetorDeTriagem();
    }

    private function seedUsuariosCliente(): void
    {
        $this->seedUsuario(
            self::CLIENTE_GESTAO_EMAIL,
            'Validação SEDUR',
            '70698543032',
            'administrador',
        );

        $this->seedUsuario(
            self::CLIENTE_PORTAL_EMAIL,
            'Requerente Demo SEDUR',
            '45317828791',
            'cidadao',
        );

        $this->command?->info('Gestão (validação): '.self::CLIENTE_GESTAO_EMAIL.' / '.self::DEMO_PASSWORD);
        $this->command?->info('Portal (requerente): '.self::CLIENTE_PORTAL_EMAIL.' / '.self::DEMO_PASSWORD);
        $this->command?->info('Perfil atual da validadora: administrador.');
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

    /**
     * A caixa do setor só lista processos dos setores do usuário. Sem este
     * vínculo, a conta de validação (e o admin de demo) vê a caixa vazia
     * mesmo com processo em "Para distribuir" na diretoria de análise.
     */
    private function vincularGestaoAoSetorDeTriagem(): void
    {
        $setor = Sector::query()
            ->where('name', SectorSeeder::SECTOR_NAME)
            ->where('active', true)
            ->first();

        if ($setor === null) {
            return;
        }

        $ids = User::query()
            ->whereIn('email', [self::CLIENTE_GESTAO_EMAIL, 'admin@sile.dev'])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $setor->analysts()->syncWithoutDetaching($ids->all());
    }
}
