<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un adjunto de la consulta (FASE 4, entrega 4.5): una imagen o un PDF en el disco privado
 * consultation_attachments (ConsultationAttachmentService). path es la ruta interna: nunca sale en
 * una respuesta ($hidden y los Resources) ni en la auditoría ("(archivo)"). original_name es el
 * nombre del archivo subido, saneado, solo para mostrar; mime_type, el tipo detectado en el contenido.
 */
class ConsultationAttachment extends Model
{
    use BelongsToClinic, Auditable;

    protected $fillable = [
        'consultation_id',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * La ruta del archivo nunca queda en la auditoría: en su lugar, "(archivo)", como la foto del
     * paciente y el logo (R-041).
     */
    public function auditValues(array $values): array
    {
        if (($values['path'] ?? null) !== null) {
            $values['path'] = SystemSetting::AUDIT_FILE;
        }

        return $values;
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }
}
