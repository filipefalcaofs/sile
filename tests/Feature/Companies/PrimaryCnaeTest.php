<?php

namespace Tests\Feature\Companies;

use App\Models\Activity;
use App\Models\Cnae;
use App\Models\Company;
use App\Services\CompanyCnaeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrimaryCnaeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_define_cnae_principal_unico(): void
    {
        $company = Company::factory()->create();
        $cnae = Cnae::factory()->create();

        app(CompanyCnaeService::class)->setPrimary($company, $cnae);

        $this->assertDatabaseHas('company_cnae', [
            'company_id' => $company->id,
            'cnae_id' => $cnae->id,
            'is_primary' => true,
        ]);
        $this->assertSame(1, $company->cnaes()->count());
        $this->assertSame(1, $company->primaryCnae()->count());
    }

    public function test_troca_de_principal_demove_o_antigo(): void
    {
        $company = Company::factory()->create();
        [$first, $second] = Cnae::factory()->count(2)->create();

        $service = app(CompanyCnaeService::class);
        $service->setPrimary($company, $first);
        $service->setPrimary($company, $second);

        // O antigo principal permanece vinculado, demovido a secundário.
        $this->assertDatabaseHas('company_cnae', [
            'company_id' => $company->id,
            'cnae_id' => $first->id,
            'is_primary' => false,
        ]);
        $this->assertSame(1, $company->primaryCnae()->count());
        $this->assertSame($second->id, $company->primaryCnae()->first()?->id);
    }

    public function test_troca_de_principal_e_auditada_com_anterior_e_novo(): void
    {
        $company = Company::factory()->create();
        [$first, $second] = Cnae::factory()->count(2)->create();

        $service = app(CompanyCnaeService::class);
        $service->setPrimary($company, $first);
        $service->setPrimary($company, $second);

        $activity = Activity::where('log_name', 'empresas')
            ->where('event', 'cnae-principal')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity da troca de CNAE principal');
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame($company->id, $activity->properties['empresa_id']);
        $this->assertSame($first->code, $activity->properties['cnae_anterior']);
        $this->assertSame($second->code, $activity->properties['cnae_novo']);
    }
}
