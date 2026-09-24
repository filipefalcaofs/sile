<?php

namespace App\Http\Controllers\Regin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class ReginDocumentacaoController extends Controller
{
    public function show(): Response
    {
        return response()->view('regin.documentacao');
    }

    public function spec(): Response
    {
        $path = resource_path('api/openapi-api-integracao-recebe.yaml');

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }
}
