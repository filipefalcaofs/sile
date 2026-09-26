<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Http\Responses\FailedPasswordResetLinkRequestResponse;

/**
 * E-mail inexistente recebe a mesma resposta de um envio real. O limite de
 * tentativas continua como erro.
 */
class RespostaRecuperacaoSenhaSemRevelarConta implements FailedPasswordResetLinkRequestResponseContract
{
    public function __construct(protected string $status) {}

    public function toResponse($request)
    {
        if ($this->status === Password::INVALID_USER) {
            /** @var Responsable $sucesso */
            $sucesso = app(SuccessfulPasswordResetLinkRequestResponse::class, [
                'status' => Password::RESET_LINK_SENT,
            ]);

            return $sucesso->toResponse($request);
        }

        return (new FailedPasswordResetLinkRequestResponse($this->status))->toResponse($request);
    }
}
