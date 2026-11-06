<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Enums\PatientSex;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Paciente. Entidad propia: su dueño (client) puede cambiar, y las consultas
 * guardan el dueño que tenía en ese momento.
 */
class Patient extends Model
{
    use BelongsToClinic, Auditable, SoftDeletes;

    protected $fillable = [
        'code',
        'client_id',
        'name',
        'species_id',
        'breed_id',
        'sex',
        'is_neutered',
        'birth_date',
        'birth_date_estimated',
        'color',
        'weight_kg',
        'microchip',
        // photo_path no: solo lo cambia PatientPhotoService, con el archivo (R-027).
        'allergies',
        'notes',
        'custom_fields',
        'deceased_at',
    ];

    protected $attributes = [
        'sex' => 'unknown',
        'is_neutered' => false,
        'birth_date_estimated' => false,
    ];

    protected function casts(): array
    {
        return [
            'sex' => PatientSex::class,
            'is_neutered' => 'boolean',
            'birth_date' => 'date',
            'birth_date_estimated' => 'boolean',
            'weight_kg' => 'decimal:2',
            'custom_fields' => 'array',
            'deceased_at' => 'datetime',
        ];
    }

    /**
     * Edad en texto ("3 años", "5 meses"), o null sin fecha de nacimiento.
     */
    protected function ageLabel(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->birth_date) {
                return null;
            }

            $diff = $this->birth_date->diff($this->deceased_at ?? now());

            if ($diff->y > 0) {
                return $diff->y . ($diff->y === 1 ? ' año' : ' años');
            }

            return $diff->m . ($diff->m === 1 ? ' mes' : ' meses');
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(PatientApplication::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
