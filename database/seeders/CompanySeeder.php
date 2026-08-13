<?php

namespace Database\Seeders;

use App\Enums\CompanyLinkRole;
use App\Enums\CompanySource;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\User;
use App\Services\RedesimImportService;
use Illuminate\Database\Seeder;

/**
 * Dados de DESENVOLVIMENTO do cadastro empresarial: um cidadão de teste
 * (cidadao@sile.dev / password) com empresas de exemplo cobrindo as duas
 * origens — manual e REDESIM (esta via import REAL do payload de
 * referência, mesma lógica de produção; muda só a carga).
 *
 * Nunca executar em produção com estas credenciais. Todos os CNPJs são
 * públicos/oficiais (dado real), com endereços em Salvador/BA plausíveis.
 * Idempotente: firstOrCreate em tudo.
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $cidadao = $this->seedCidadao();
        $this->seedEmpresaManual($cidadao);
        $this->seedEmpresasRedesim($cidadao);
    }

    private function seedCidadao(): User
    {
        $cidadao = User::firstOrCreate(
            ['email' => 'cidadao@sile.dev'],
            [
                'name' => 'Cidadão SILE',
                'cpf' => '52998224725',
                'phone' => null,
                'password' => 'password',
            ],
        );

        if ($cidadao->email_verified_at === null) {
            $cidadao->forceFill(['email_verified_at' => now()])->save();
        }

        $cidadao->assignRole('cidadao');

        if ($term = LegalTerm::current('lgpd')) {
            LegalTermAcceptance::firstOrCreate(
                ['user_id' => $cidadao->id, 'legal_term_id' => $term->id],
                ['ip_address' => '127.0.0.1', 'accepted_at' => now()],
            );
        }

        return $cidadao;
    }

    private function seedEmpresaManual(User $cidadao): void
    {
        $company = Company::firstOrCreate(
            ['cnpj' => '47960950000121'],
            [
                'legal_name' => 'MAGAZINE LUIZA S/A',
                'trade_name' => 'MAGALU',
                'legal_nature_code' => '2054',
                'legal_nature' => 'Sociedade Anônima Aberta',
                'size_code' => '05',
                'size' => 'DEMAIS',
                'street' => 'Avenida Tancredo Neves',
                'number' => '999',
                'complement' => 'Loja 1',
                'neighborhood' => 'Caminho das Árvores',
                'city' => 'Salvador',
                'state' => 'BA',
                'zip_code' => '41820021',
                'email' => 'contato@magazineluiza.dev',
                'phone' => '7130000000',
                'source' => CompanySource::Manual,
            ],
        );

        CompanyUser::firstOrCreate(
            ['company_id' => $company->id, 'user_id' => $cidadao->id, 'role' => CompanyLinkRole::Responsavel],
            ['started_at' => now()],
        );

        // CNAE principal: 4713-0/04 (lojas de departamentos/magazines).
        $principal = Cnae::query()->where('code', '4713004')->first()
            ?? Cnae::query()->where('active', true)->orderBy('code')->first();

        if ($principal && $company->primaryCnae()->doesntExist()) {
            $company->cnaes()->syncWithoutDetaching([$principal->id => ['is_primary' => true]]);
        }
    }

    private function seedEmpresasRedesim(User $cidadao): void
    {
        // Import REAL do payload de referência (lógica de produção, carga de
        // dev). O serviço NÃO cria vínculo usuário-empresa por design — o
        // vínculo abaixo é conveniência de DEV para a empresa aparecer em
        // "Minhas empresas" (a associação real chega na Fase 13).
        app(RedesimImportService::class)->import(database_path('data/redesim-exemplo.json'));

        $company = Company::query()->where('cnpj', '00000000000191')->first();

        if ($company) {
            CompanyUser::firstOrCreate(
                ['company_id' => $company->id, 'user_id' => $cidadao->id, 'role' => CompanyLinkRole::Responsavel],
                ['started_at' => now()],
            );
        }
    }
}
