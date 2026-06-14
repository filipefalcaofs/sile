<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RiscoController extends Controller
{
    // Esqueleto criado no 06-06 Task 1 para que as rotas resolvam; a consulta
    // da tabela vigente (HU-052) e a publicação versionada (HU-053) são
    // implementadas na Task 2 via TDD.
    public function index(Request $request): mixed
    {
        abort(HttpResponse::HTTP_NOT_IMPLEMENTED);
    }

    public function publish(Request $request): mixed
    {
        abort(HttpResponse::HTTP_NOT_IMPLEMENTED);
    }
}
