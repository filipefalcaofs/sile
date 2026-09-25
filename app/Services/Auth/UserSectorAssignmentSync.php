<?php

namespace App\Services\Auth;

use App\Models\Sector;
use App\Models\User;
use App\Models\UserSectorAssignment;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza o vínculo usuário×setor a partir da lotação do discovery AD.
 * Cria o setor se a API indicar um inexistente; troca de setor encerra a
 * vigência anterior e abre nova linha em user_sector_assignments.
 */
class UserSectorAssignmentSync
{
    public const SOURCE_DISCOVERY = 'discovery';

    public function __construct(private AuditService $audit) {}

    public function syncFromDiscovery(User $user, ?string $code, ?string $name): void
    {
        $code = $code !== null ? trim($code) : null;
        $name = $name !== null ? trim($name) : null;

        if (($code === null || $code === '') && ($name === null || $name === '')) {
            return;
        }

        DB::transaction(function () use ($user, $code, $name) {
            $sector = $this->resolveSector($code, $name);

            $current = UserSectorAssignment::query()
                ->where('user_id', $user->id)
                ->where('source', self::SOURCE_DISCOVERY)
                ->whereNull('ended_at')
                ->first();

            if ($current !== null && (int) $current->sector_id === (int) $sector->id) {
                $user->sectors()->syncWithoutDetaching([$sector->id]);

                return;
            }

            $previousSectorId = $current?->sector_id;

            if ($current !== null) {
                $current->update(['ended_at' => now()]);
                $user->sectors()->detach($current->sector_id);
            }

            $user->sectors()->syncWithoutDetaching([$sector->id]);

            UserSectorAssignment::query()->create([
                'user_id' => $user->id,
                'sector_id' => $sector->id,
                'previous_sector_id' => $previousSectorId,
                'source' => self::SOURCE_DISCOVERY,
                'started_at' => now(),
                'ended_at' => null,
                'changed_by_user_id' => null,
            ]);

            $this->audit->log('setores', 'sincronizar-lotacao-discovery', 'Lotação sincronizada via discovery AD', [
                'user_id' => $user->id,
                'sector_id' => $sector->id,
                'sector_code' => $sector->code,
                'previous_sector_id' => $previousSectorId,
                'source' => self::SOURCE_DISCOVERY,
            ], $sector);
        });
    }

    private function resolveSector(?string $code, ?string $name): Sector
    {
        if ($code !== null && $code !== '') {
            $byCode = Sector::query()->where('code', $code)->first();

            if ($byCode !== null) {
                if ($name !== null && $name !== '' && $byCode->name !== $name) {
                    $byCode->update(['name' => $name, 'active' => true]);
                } elseif (! $byCode->active) {
                    $byCode->update(['active' => true]);
                }

                return $byCode->fresh();
            }
        }

        if ($name !== null && $name !== '') {
            $byName = Sector::query()->where('name', $name)->first();

            if ($byName !== null) {
                if ($code !== null && $code !== '' && $byName->code !== $code) {
                    $byName->update(['code' => $code, 'active' => true]);
                } elseif (! $byName->active) {
                    $byName->update(['active' => true]);
                }

                return $byName->fresh();
            }
        }

        return Sector::query()->create([
            'code' => $code !== '' ? $code : null,
            'name' => $name !== null && $name !== ''
                ? $name
                : 'Setor CEIN '.($code ?? 'desconhecido'),
            'active' => true,
        ]);
    }
}
