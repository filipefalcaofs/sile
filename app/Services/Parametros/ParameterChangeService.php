<?php

namespace App\Services\Parametros;

use App\Enums\ParameterProposalStatus;
use App\Exceptions\FourEyesViolationException;
use App\Models\Parameter;
use App\Models\ParameterProposal;
use App\Support\Audit\AuditService;
use DomainException;
use Illuminate\Support\Carbon;

/**
 * Rito de alteração de parâmetro decisório: a proposta coexiste com o valor
 * vigente e só aplica quando um segundo usuário aprova (quatro olhos).
 */
class ParameterChangeService
{
    public function __construct(private AuditService $audit) {}

    public function isUnchanged(Parameter $parameter, string $value): bool
    {
        return (string) ($parameter->value ?? $parameter->default_value) === $value;
    }

    public function propose(Parameter $parameter, string $value, int $authorId): ParameterProposal
    {
        if ($this->isUnchanged($parameter, $value)) {
            throw new DomainException('Valor inalterado.');
        }

        ParameterProposal::query()
            ->where('parameter_id', $parameter->id)
            ->where('status', ParameterProposalStatus::Pending)
            ->update([
                'status' => ParameterProposalStatus::Rejected,
                'reviewed_by' => $authorId,
                'reviewed_at' => Carbon::now(),
            ]);

        return ParameterProposal::query()->create([
            'parameter_id' => $parameter->id,
            'proposed_value' => $value,
            'created_by' => $authorId,
            'status' => ParameterProposalStatus::Pending,
        ]);
    }

    public function approve(ParameterProposal $proposal, int $reviewerId): Parameter
    {
        if ($proposal->status !== ParameterProposalStatus::Pending) {
            throw new DomainException('Não há proposta pendente para aprovar.');
        }

        if ($reviewerId === $proposal->created_by) {
            throw new FourEyesViolationException(
                'A publicação por quatro olhos exige um publicador diferente do autor do rascunho.',
            );
        }

        $parameter = $proposal->parameter;
        $old = $parameter->value;
        $parameter->update(['value' => $proposal->proposed_value]);

        $proposal->update([
            'status' => ParameterProposalStatus::Approved,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => Carbon::now(),
        ]);

        $this->audit->log(
            'parametros',
            'parametro-aprovado',
            "Parâmetro {$parameter->key} aprovado por quatro olhos",
            [
                'key' => $parameter->key,
                'autor_id' => $proposal->created_by,
                'aprovador_id' => $reviewerId,
                'valor_anterior' => $parameter->sensitive ? '[criptografado]' : $old,
                'valor_novo' => $parameter->sensitive ? '[criptografado]' : $proposal->proposed_value,
            ],
            $parameter,
        );

        return $parameter->refresh();
    }

    public function reject(ParameterProposal $proposal, int $reviewerId): ParameterProposal
    {
        if ($proposal->status !== ParameterProposalStatus::Pending) {
            throw new DomainException('Não há proposta pendente para rejeitar.');
        }

        $proposal->update([
            'status' => ParameterProposalStatus::Rejected,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => Carbon::now(),
        ]);

        $parameter = $proposal->parameter;

        $this->audit->log(
            'parametros',
            'parametro-rejeitado',
            "Proposta do parâmetro {$parameter->key} rejeitada",
            [
                'key' => $parameter->key,
                'autor_id' => $proposal->created_by,
                'revisor_id' => $reviewerId,
                'valor_proposto' => $parameter->sensitive ? '[criptografado]' : $proposal->proposed_value,
            ],
            $parameter,
        );

        return $proposal->refresh();
    }
}
