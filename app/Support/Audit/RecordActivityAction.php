<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Spatie\Activitylog\Actions\LogActivityAction;

/**
 * Ponto único de enriquecimento da RN-002: toda activity — de model event ou
 * chamada manual — recebe origem (ip/user_agent/channel), "em nome de"
 * (Context) e resultado padrão antes de persistir.
 */
class RecordActivityAction extends LogActivityAction
{
    protected function save(Model $activity): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            $activity->channel ??= 'console';
        } elseif (request()) {
            $activity->ip_address ??= request()->ip();
            $activity->user_agent ??= substr((string) request()->userAgent(), 0, 500);
            $activity->channel ??= request()->routeIs('gestao.*') ? 'gestao' : 'portal';
            $activity->acting_for_user_id ??= Context::get('acting_for_user_id');
        }

        $activity->result ??= 'sucesso';

        parent::save($activity);
    }
}
