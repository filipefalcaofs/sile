<?php

namespace App\Providers;

use App\Services\Analise\PostgisPrecedentRepository;
use App\Services\Analise\PrecedentRepository;
use App\Services\Cnpj\BrasilApiCnpjLookup;
use App\Services\Cnpj\CnpjLookup;
use App\Services\Geo\Geocoder;
use App\Services\Geo\NominatimGeocoder;
use App\Services\Geo\PostgisSpatialRepository;
use App\Services\Geo\SpatialRepository;
use App\Services\GovBr\GovBrIdTokenValidator;
use App\Services\GovBr\GovBrProvider;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\UnavailablePropertyRegistryLookup;
use App\Services\Regin\BapRegistry;
use App\Services\Regin\ReginParecerNotifier;
use App\Services\Regin\UnavailableBapRegistry;
use App\Services\Regin\UnavailableReginParecerNotifier;
use App\Services\Sefaz\SefazViabilidadeGateway;
use App\Services\Sefaz\UnavailableSefazViabilidadeGateway;
use App\Support\Representation\CurrentRepresentation;
use App\Support\Settings;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentRepresentation::class);

        // Provider público inicial (BrasilAPI / dados abertos RFB). A Fase 13
        // (HU-105) troca este binding pelo provider conveniado da Receita
        // Federal sem tocar controllers ou telas.
        $this->app->bind(CnpjLookup::class, BrasilApiCnpjLookup::class);

        // Geocodificação pública inicial (Nominatim/OSM). A Fase 13 troca este
        // binding por self-host ou pela base geográfica da SEDUR sem tocar
        // controllers ou telas (HU-029).
        $this->app->bind(Geocoder::class, NominatimGeocoder::class);

        // SQL espacial real (PostGIS) atrás de contrato (HU-031 a HU-035): o
        // TerritoryService e o motor da Fase 5 dependem da interface, não do
        // SQL — os testes dos consumidores usam um fake em memória.
        $this->app->bind(SpatialRepository::class, PostgisSpatialRepository::class);

        // Precedentes da análise técnica (HU-142) atrás de contrato, espelhando
        // o SpatialRepository: o PrecedentService depende da interface, não do
        // SQL espacial (ST_Intersects sobre property_polygon). Binding
        // INCONDICIONAL → PostgisPrecedentRepository; os testes SQLite injetam um
        // fake em memória via $this->app->instance, nunca o fake no container de
        // produção.
        $this->app->bind(PrecedentRepository::class, PostgisPrecedentRepository::class);

        // Resolução por inscrição imobiliária (HU-055) atrás de contrato. A base
        // de lotes/Cadastro está PENDENTE SEDUR — o provider degrada honestamente
        // (nunca inventa ponto). A Fase 13 (HU-106) troca SÓ este binding.
        $this->app->bind(PropertyRegistryLookup::class, UnavailablePropertyRegistryLookup::class);

        // Integrações de saída do fluxo expresso BLOQUEADAS (sem contrato/
        // homologação): comunicar o parecer ao Regin/Junta (HU-104) e enviar a
        // viabilidade à SEFAZ municipal (HU-110) degradam HONESTO — o provider
        // Unavailable LANÇA exceção (a transmissão não ocorreu), nunca simula
        // sucesso; o vínculo BAP (HU-134) retorna null (sem vínculo, nada entra
        // em aguardando_bap). A Fase 13 troca SÓ estes bindings, sem tocar os
        // listeners que os consomem (09-08/09/10).
        $this->app->bind(ReginParecerNotifier::class, UnavailableReginParecerNotifier::class);
        $this->app->bind(SefazViabilidadeGateway::class, UnavailableSefazViabilidadeGateway::class);
        $this->app->bind(BapRegistry::class, UnavailableBapRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Os listeners em app/Listeners são registrados pela auto-descoberta de
        // eventos do Laravel (cada um faz type-hint do evento no handle): a poda
        // de retenção (ModelsPruned → AuditModelsPruned, RN-002/SC#1), o envio de
        // notificação (NotificationSent → LogNotificationSent) e o PRIMEIRO evento
        // de domínio (SolicitacaoProtocolada → RegistrarTrilhaProtocolo, HU-068,
        // que grava o marco da timeline e a auditoria de alto nível). NÃO registrar
        // manualmente aqui: o Event::listen duplicaria o registro (auditoria 2×).
        // Fases futuras só ADICIONAM classes de listener ao mesmo evento
        // (notificação EP11, elegibilidade EP09, resposta Regin EP13).

        Password::defaults(function () {
            $rule = Password::min((int) Settings::get('security.password.min_length', 8));

            if (Settings::get('security.password.require_mixed_case', true)) {
                $rule->mixedCase();
            }

            if (Settings::get('security.password.require_numbers', true)) {
                $rule->numbers();
            }

            if (Settings::get('security.password.require_symbols', false)) {
                $rule->symbols();
            }

            return $rule;
        });

        config(['auth.passwords.users.expire' => (int) Settings::get('security.password_reset_expire', 60)]);

        // Driver Socialite do Login Único (HU-151). Credenciais e URL são
        // lidas dos parâmetros administráveis A CADA resolução do driver —
        // trocar staging/produção ou rotacionar credencial não exige deploy.
        Socialite::extend('govbr', function ($app): GovBrProvider {
            $provider = new GovBrProvider(
                $app['request'],
                (string) Settings::get('integrations.govbr.client_id', ''),
                (string) Settings::get('integrations.govbr.client_secret', ''),
                route('portal.govbr.callback'),
            );

            return $provider
                ->withBaseUrl((string) Settings::get('integrations.govbr.base_url'))
                ->withIdTokenValidator($app->make(GovBrIdTokenValidator::class));
        });
    }
}
