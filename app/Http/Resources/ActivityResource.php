<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload de leitura de um registro da trilha de auditoria para a consulta
 * unificada (HU-100) e o histórico de alterações (HU-098). Expõe a RN-002
 * (quem/quando/origem/ação/resultado/versão de regras), o causer (null =
 * sistema), o "em nome de" (representação), o subject (tipo legível + id) e o
 * `attribute_changes` (diff p/ HU-098). Por minimização (LGPD) NÃO devolve as
 * `properties` cruas — o diff já é o recorte estruturado e seguro da mudança.
 *
 * Consumido como prop do Inertia (->resolve()), sem o wrapper "data". A tela
 * (gestao/auditoria/index) é construída em 12-10.
 *
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'log_name' => $this->log_name,
            'event' => $this->event,
            'description' => $this->description,
            'causer' => $this->resumoUsuario($this->causer),
            'acting_for' => $this->resumoUsuario($this->actingFor),
            'subject' => $this->resumoSubject(),
            'result' => $this->result,
            'rules_version' => $this->rules_version,
            'ip_address' => $this->ip_address,
            'channel' => $this->channel,
            'personal_data' => (bool) $this->personal_data,
            // Diff estruturado da mudança (HU-098): ['attributes' => ..., 'old' => ...].
            'attribute_changes' => $this->attribute_changes?->toArray(),
        ];
    }

    /**
     * Resumo de um usuário relacionado (causer ou representado). Null quando não
     * há vínculo — causer null significa ação do próprio sistema.
     *
     * @return array{id: int, nome: string|null}|null
     */
    private function resumoUsuario(?Model $usuario): ?array
    {
        if ($usuario === null) {
            return null;
        }

        return [
            'id' => $usuario->getKey(),
            'nome' => $usuario->getAttribute('name'),
        ];
    }

    /**
     * Resumo do subject auditado: tipo legível (basename da classe) + id, com um
     * rótulo amigável quando o registro ainda existe (protocolo/nome/título).
     *
     * @return array{type: string, id: int|null, label: string|null}|null
     */
    private function resumoSubject(): ?array
    {
        if ($this->subject_type === null) {
            return null;
        }

        return [
            'type' => class_basename($this->subject_type),
            'id' => $this->subject_id,
            'label' => $this->rotuloSubject(),
        ];
    }

    private function rotuloSubject(): ?string
    {
        $subject = $this->subject;

        if ($subject === null) {
            return null;
        }

        foreach (['protocol_number', 'name', 'title', 'legal_name', 'trade_name'] as $atributo) {
            $valor = $subject->getAttribute($atributo);

            if (is_string($valor) && $valor !== '') {
                return $valor;
            }
        }

        return null;
    }
}
