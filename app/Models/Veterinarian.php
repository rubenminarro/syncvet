<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Enums\VeterinarianStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ficha del veterinario (FASE 2, entrega 2.3): una por usuario (veterinarians.user_id
 * es único en toda la base, también entre eliminadas), con el rol veterinario. Sus
 * sucursales son las de su usuario (P5). Un alta del mismo usuario restaura la ficha
 * eliminada y conserva su código VET- (P6).
 *
 * user_id y code no cambian al editar: los fija VeterinarianService.
 */
class Veterinarian extends Model
{
    use BelongsToClinic, Auditable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'code',
        'license_number',
        'specialty',
        'commission_percentage',
        'status',
        'notes',
    ];

    /**
     * Los valores por defecto de las columnas: una ficha recién creada ya los muestra.
     */
    protected $attributes = [
        'status' => 'active',
        'commission_percentage' => '0.00',
    ];

    protected function casts(): array
    {
        return [
            'status' => VeterinarianStatus::class,
            'commission_percentage' => 'decimal:2',
        ];
    }

    /**
     * El usuario, también eliminado: la ficha eliminada lo conserva.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
