<?php

namespace Tests\Feature\Companies;

use App\Models\Activity;
use App\Models\Cnae;
use App\Models\Company;
use App\Services\CompanyCnaeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecondaryCnaesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_sincroniza_conjunto_exato_de_secundarios(): void
    {
        $primary = Cnae::factory()->create();
        $company = Company::factory()->withPrimaryCnae($primary)->create();
        [$b, $c, $d] = Cnae::factory()->count(3)->create();

        $service = app(CompanyCnaeService::class);
        $service->syncSecondaries($company, [$b->id, $c->id]);
        $service->syncSecondaries($company, [$c->id, $d->id]);

        $secondaryIds = $company->cnaes()->wherePivot('is_primary', false)->pluck('cnaes.id');

        $this->assertEqualsCanonicalizing([$c->id, $d->id], $secondaryIds->all());
        $this->assertSame(1, $company->primaryCnae()->count());
        $this->assertSame($primary->id, $company->primaryCnae()->first()?->id);
    }

    public function test_remocao_total_de_secundarios_e_permitida(): void
    {
        $primary = Cnae::factory()->create();
        $company = Company::factory()->withPrimaryCnae($primary)->create();
        $b = Cnae::factory()->create();

        $service = app(CompanyCnaeService::class);
        $service->syncSecondaries($company, [$b->id]);
        $service->syncSecondaries($company, []);

        $this->assertSame(0, $company->cnaes()->wherePivot('is_primary', false)->count());
        $this->assertSame($primary->id, $company->primaryCnae()->first()?->id);
    }

    public function test_sincronizacao_e_auditada_com_antes_e_depois(): void
    {
        $primary = Cnae::factory()->create();
        $company = Company::factory()->withPrimaryCnae($primary)->create();
        [$b, $c] = Cnae::factory()->count(2)->create();

        app(CompanyCnaeService::class)->syncSecondaries($company, [$b->id, $c->id]);

        $activity = Activity::where('log_name', 'empresas')
            ->where('event', 'cnaes-secundarios')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity da sincronização de secundários');
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame($company->id, $activity->properties['empresa_id']);
        $this->assertSame([], $activity->properties['antes']);
        $this->assertEqualsCanonicalizing([$b->code, $c->code], $activity->properties['depois']);
    }
}
