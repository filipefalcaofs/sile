<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\RiskCondicionante;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RiscoCondicionanteController extends Controller
{
    // Esqueleto criado no 06-06 Task 1 para que as rotas resolvam; o CRUD de
    // condicionantes-pergunta (HU-019) é implementado na Task 3 via TDD.
    public function index(Request $request): mixed
    {
        abort(HttpResponse::HTTP_NOT_IMPLEMENTED);
    }

    public function store(Request $request): mixed
    {
        abort(HttpResponse::HTTP_NOT_IMPLEMENTED);
    }

    public function update(Request $request, RiskCondicionante $condicionante): mixed
    {
        abort(HttpResponse::HTTP_NOT_IMPLEMENTED);
    }

    public function destroy(RiskCondicionante $condicionante): mixed
    {
        abort(HttpResponse::HTTP_NOT_IMPLEMENTED);
    }
}
