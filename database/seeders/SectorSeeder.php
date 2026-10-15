<?php

namespace Database\Seeders;

use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\Parameter;
use App\Models\Sector;
use App\Models\User;
use App\Services\Sector\OfficialSectorCatalogSync;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo oficial de setores (CEIN) + usuários de gestão de DESENVOLVIMENTO
 * vinculados à caixa de triagem.
 *
 * O catálogo vem da planilha oficial (OfficialSectorCatalogSync). Em produção
 * a migration de carga já sincroniza; o seeder re-sincroniza de forma
 * idempotente. O analista, o gestor e o apoio de DEV (analista@sile.dev /
 * gestor@sile.dev / apoio@sile.dev, senha `password`) são criados SÓ em
 * dev/teste — dados fictícios para tornar a caixa, a distribuição (HU-080),
 * a assunção (HU-081) e a ficha (HU-135) navegáveis. Nunca executar em
 * produção com estas credenciais.
 *
 * A caixa de triagem do motor aponta para o Setor de Triagem e Distribuição
 * (CEIN 0231400300) enquanto o roteamento fino por tipo de processo não chega
 * da SEDUR. Idempotente: sync por código/nome; vínculo N:N com
 * syncWithoutDetaching. Depende de RolesAndPermissionsSeeder e LegalTermSeeder.
 */
class SectorSeeder extends Seeder
{
    /** @deprecated Use OfficialSectorCatalogSync::TRIAGEM_NAME */
    public const SECTOR_NAME = OfficialSectorCatalogSync::TRIAGEM_NAME;

    public const SECTOR_CODE = OfficialSectorCatalogSync::TRIAGEM_CODE;

    public function run(): void
    {
        if (! Schema::hasColumn((new Sector)->getTable(), 'code')) {
            return;
        }

        app(OfficialSectorCatalogSync::class)->sync();

        $sector = Sector::query()
            ->where(function ($query) {
                $query->where('code', self::SECTOR_CODE)
                    ->orWhere('name', self::SECTOR_NAME);
            })
            ->firstOrFail();

        // Usuários de gestão são dados de DEV: nunca em produção (lá os analistas
        // reais são cadastrados pelo administrador e vinculados pelo gestor).
        if (! app()->environment(['local', 'testing']) && ! filter_var(config('viabiliza.demo_data', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $analista = $this->seedGestaoUser('analista@sile.dev', 'Analista Viabiliza', '39053344705', 'Analista Terceirizado');
        $gestor = $this->seedGestaoUser('gestor@sile.dev', 'Gestor Viabiliza', '48795515006', 'Coordenador');
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
