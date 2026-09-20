<?php

namespace Database\Seeders;

use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\Parameter;
use App\Models\Sector;
use App\Models\User;
use App\Support\DemoMode;
use Illuminate\Database\Seeder;

/**
 * Setor da SEDUR (HU-138) — a caixa de distribuição da análise técnica — e os
 * usuários de gestão de DESENVOLVIMENTO vinculados a ele.
 *
 * O setor "Análise Locacional" é criado SEMPRE (catálogo padrão): em produção o
 * gestor mantém os setores pela interface (HU-138), mas um setor inicial evita
 * que a distribuição nasça bloqueada. Já o analista e o gestor de DEV
 * (analista@sile.dev / gestor@sile.dev, ambos `password`) são criados SÓ em
 * dev/teste — dados fictícios para tornar a caixa, a distribuição (HU-080), a
 * assunção (HU-081) e a ficha (HU-135) navegáveis de ponta a ponta. Nunca
 * executar em produção com estas credenciais.
 *
 * Idempotente: firstOrCreate por nome (setor) e e-mail (usuários); o vínculo
 * N:N usa syncWithoutDetaching. Depende de RolesAndPermissionsSeeder (papéis) e
 * LegalTermSeeder (termo LGPD).
 */
class SectorSeeder extends Seeder
{
    public const SECTOR_NAME = 'Análise Locacional';

    public function run(): void
    {
        $sector = Sector::firstOrCreate(
            ['name' => self::SECTOR_NAME],
            ['active' => true],
        );

        // Usuários de gestão são dados de DEV: nunca em produção (lá os analistas
        // reais são cadastrados pelo administrador e vinculados pelo gestor).
        if (! DemoMode::allowsDemoSeeders()) {
            return;
        }

        $analista = $this->seedGestaoUser('analista@sile.dev', 'Analista Viabiliza', '39053344705', 'analista');
        $gestor = $this->seedGestaoUser('gestor@sile.dev', 'Gestor Viabiliza', '48795515006', 'gestor');
        $apoio = $this->seedGestaoUser('apoio@sile.dev', 'Apoio Viabiliza', '71602913013', 'apoio');

        // Vincula os três ao setor (HU-138 RN-005): o apoio/gestor distribuem e
        // o analista assume os processos da caixa.
        $sector->analysts()->syncWithoutDetaching([$analista->id, $gestor->id, $apoio->id]);

        // Elo motor → caixa do setor (analise.setor_triagem_id): em dev o setor
        // padrão recebe os processos que o motor encaminha à análise, tornando o
        // fluxo caixa → apoio → analista navegável. Só preenche quando VAZIO —
        // valor administrado pela interface (HU-014) é preservado no re-seed.
        Parameter::query()
            ->where('key', 'analise.setor_triagem_id')
            ->whereNull('value')
            ->update(['value' => (string) $sector->id]);
    }

    /**
     * Cria (ou reusa) um usuário de gestão de dev com papel, e-mail verificado e
     * termo LGPD aceito — pronto para acessar a gestão sem o aceite manual.
     */
    private function seedGestaoUser(string $email, string $name, string $cpf, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'cpf' => $cpf,
                'phone' => null,
                'password' => 'password',
            ],
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->assignRole($role);

        if ($term = LegalTerm::current('lgpd')) {
            LegalTermAcceptance::firstOrCreate(
                ['user_id' => $user->id, 'legal_term_id' => $term->id],
                ['ip_address' => '127.0.0.1', 'accepted_at' => now()],
            );
        }

        return $user;
    }
}
