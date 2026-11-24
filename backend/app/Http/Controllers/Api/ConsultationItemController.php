<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConsultationItemRequest;
use App\Http\Requests\UpdateConsultationItemRequest;
use App\Http\Resources\ShowConsultationResource;
use App\Models\Consultation;
use App\Models\ConsultationItem;
use App\Services\ConsultationItemService;
use App\Services\ConsultationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Los servicios usados en la consulta (FASE 4, entrega 4.3): sumar (POST
 * consultations/{consultation}/items), editar y quitar (PATCH y DELETE consultation-items/{item}),
 * solo con la consulta abierta, por su veterinario o el administrador. Cada respuesta trae el detalle
 * de la consulta, con sus ítems.
 */
class ConsultationItemController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected ConsultationItemService $items,
        protected ConsultationService $consultations
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:consultation-items.store', only: ['store']),
            new Middleware('permission:consultation-items.update', only: ['update']),
            new Middleware('permission:consultation-items.destroy', only: ['destroy']),
        ];
    }

    public function store(StoreConsultationItemRequest $request, Consultation $consultation)
    {
        $consultation = $this->items->add($consultation, $request->validated(), $request->user());

        return $this->successResponse(
            'Servicio sumado a la consulta.',
            new ShowConsultationResource($this->consultations->loadDetail($consultation)),
            201
        );
    }

    public function update(UpdateConsultationItemRequest $request, ConsultationItem $item)
    {
        $consultation = $this->items->update($item, $request->validated(), $request->user());

        return $this->successResponse(
            'Ítem actualizado correctamente.',
            new ShowConsultationResource($this->consultations->loadDetail($consultation)),
            200
        );
    }

    public function destroy(Request $request, ConsultationItem $item)
    {
        $consultation = $this->items->remove($item, $request->user());

        return $this->successResponse(
            'Ítem quitado de la consulta.',
            new ShowConsultationResource($this->consultations->loadDetail($consultation)),
            200
        );
    }
}
