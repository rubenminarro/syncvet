<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Species;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Especies activas con sus razas activas, para los selectores del alta de paciente.
 */
class SpeciesController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:species.index', only: ['index']),
        ];
    }

    public function index()
    {
        $species = Species::query()
            ->where('is_active', true)
            ->with(['breeds' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Species $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'breeds' => $s->breeds->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values(),
            ]);

        return $this->successResponse('Especies obtenidas correctamente.', $species, 200);
    }
}
