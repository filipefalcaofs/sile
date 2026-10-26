<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Painel\ConvitesDoCidadao;
use App\Support\Representation\CurrentRepresentation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Convites que o cidadão pode responder: os dos processos de que é
 * requerente, abertos e no prazo. A resposta é na tela de pendências.
 */
class ConviteController extends Controller
{
    public function index(Request $request, ConvitesDoCidadao $convites): Response
    {
        $efetivo = app(CurrentRepresentation::class)->effectiveUser($request->user());

        return Inertia::render('portal/convites/index', [
            'convites' => $convites->abertos($efetivo),
        ]);
    }
}
