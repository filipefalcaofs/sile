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
 * Setores da SUCOM (HU-138) — caixas de distribuição da análise — e os
 * usuários de gestão de DESENVOLVIMENTO vinculados à diretoria de análise.
 *
 * O catálogo oficial é criado SEMPRE, na ordem do cadastro legado, sem
 * SUCOM-Simplifica e sem Recursos. Em produção o gestor mantém os setores
 * pela interface (HU-138). O analista, o gestor e o apoio de DEV
 * (analista@sile.dev / gestor@sile.dev / apoio@sile.dev, senha `password`)
 * são criados SÓ em dev/teste — dados fictícios para tornar a caixa, a
 * distribuição (HU-080), a assunção (HU-081) e a ficha (HU-135) navegáveis.
 * Nunca executar em produção com estas credenciais.
 *
 * A caixa de triagem do motor aponta para a Diretoria Geral de Análise e
 * Licenciamento enquanto o roteamento fino por tipo de processo não chega
 * da SEDUR. Idempotente: firstOrCreate por nome (setor) e e-mail (usuários);
 * o vínculo N:N usa syncWithoutDetaching. Depende de RolesAndPermissionsSeeder
 * (papéis) e LegalTermSeeder (termo LGPD).
 */
class SectorSeeder extends Seeder
{
    /**
     * Cadastro legado da SUCOM, sem SUCOM-Simplifica e sem Recursos.
     *
     * @var list<string>
     */
    public const NAMES = [
        'SUCOM-DGAL-CAP-SEVAP-Setor de Vistoria Técnica',
        'SUCOM-DGAL-CPE-SEVPE-Setor de Vistoria Técnica',
        'SUCOM-ASURG-Assessoria de Urbanismo e Gestão',
        'SUCOM-DGAL-Diretoria Geral de Análise e Licenciamento',
        'SUCOM-DFIS-CFS-Coordenadoria de Fiscalização Urbanística e Segurança',
        'SUCOM-DGAL-CLA-Coordenadoria de Licenciamento Ambiental',
        'SUCOM-DFIS-CFA-Coordenadoria de Fiscalização Ambiental',
        'SUCOM-CAD-Coordenadoria Administrativa',
        'SUCOM-GAB-Gabinete',
        'SUCOM-CTI-Coordenadoria de Tecnologia da Informação',
        'SUCOM-DGAL-CEM-Vistoriadores',
        'SUCOM-DGAL-CAP-Coordenadoria de Atividade e Publicidade',
        'SUCOM-GECAT-Gerência de Cadastro Técnico',
        'SUCOM-DFIS-CFS-MPEL-SEHAB-Setor de Licenciamento de Obra Concluída',
        'SUCOM-DGAL-CEM-Coordenadoria de Empreendimentos',
        'SUCOM-DGAL-CPE-Coordenadoria de Processos Especiais',
        'SUCOM-CTI-INFO-SEATE-Setor de Atendimento e Informações',
        'SUCOM-DDU-Diretoria de Desenvolvimento Urbano',
    ];

    public const SECTOR_NAME = 'SUCOM-DGAL-Diretoria Geral de Análise e Licenciamento';

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Sector::firstOrCreate(
                ['name' => $name],
                ['active' => true],
            );
        }

        $sector = Sector::query()->where('name', self::SECTOR_NAME)->firstOrFail();

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
