<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConsultationAttachmentRequest;
use App\Http\Resources\ShowConsultationResource;
use App\Models\Consultation;
use App\Models\ConsultationAttachment;
use App\Services\ConsultationAttachmentService;
use App\Services\ConsultationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Los adjuntos de la consulta (FASE 4, entrega 4.5): subir (POST consultations/{consultation}/attachments,
 * multipart), descargar (GET consultations/{consultation}/attachments/{attachment}, el archivo, sin
 * ApiResponse; sus errores sí) y quitar (DELETE consultation-attachments/{attachment}). Subir y quitar
 * responden el detalle de la consulta; ninguna respuesta lleva la ruta del archivo. La recepción no
 * tiene estos permisos (ni ve los adjuntos en el detalle: consultations.show-clinical).
 */
class ConsultationAttachmentController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected ConsultationAttachmentService $attachments,
        protected ConsultationService $consultations
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:consultation-attachments.index', only: ['download']),
            new Middleware('permission:consultation-attachments.store', only: ['store']),
            new Middleware('permission:consultation-attachments.destroy', only: ['destroy']),
        ];
    }

    public function store(StoreConsultationAttachmentRequest $request, Consultation $consultation)
    {
        $consultation = $this->attachments->upload($consultation, $request->file('file'), $request->detectedMimeType(), $request->user());

        return $this->successResponse(
            'Adjunto sumado a la consulta.',
            new ShowConsultationResource($this->consultations->loadDetail($consultation)),
            201
        );
    }

    public function download(Consultation $consultation, ConsultationAttachment $attachment)
    {
        return $this->attachments->download($consultation, $attachment);
    }

    public function destroy(Request $request, ConsultationAttachment $attachment)
    {
        $consultation = $this->attachments->remove($attachment, $request->user());

        return $this->successResponse(
            'Adjunto quitado de la consulta.',
            new ShowConsultationResource($this->consultations->loadDetail($consultation)),
            200
        );
    }
}
