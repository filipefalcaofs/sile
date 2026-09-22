<?php

namespace App\Http\Controllers\Regin;

use App\Http\Controllers\Controller;
use App\Services\Regin\ReginRecebeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class ReginRecebeController extends Controller
{
    public function __construct(private ReginRecebeService $recebe) {}

    public function ping(): Response
    {
        return $this->codigo('3');
    }

    public function store(Request $request): Response
    {
        try {
            return $this->codigo($this->recebe->receive($request->all()));
        } catch (InvalidArgumentException) {
            return $this->codigo('0', 400);
        }
    }

    private function codigo(string $codigo, int $status = 200): Response
    {
        return response($codigo, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
