<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Enums\ClientKind;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cliente: tutor de una o más mascotas. Persona o empresa.
 */
class Client extends Model
{
    use BelongsToClinic, Auditable, SoftDeletes;

    protected $fillable = [
        'kind',
        'document_number',
        'first_name',
        'last_name',
        'business_name',
        'phone',
        'email',
        'address',
        'city',
        'notifications_enabled',
        'notes',
    ];

    protected $attributes = [
        'kind' => 'person',
        'notifications_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'kind' => ClientKind::class,
            'notifications_enabled' => 'boolean',
        ];
    }

    /**
     * Nombre para mostrar: "nombre apellido" o la razón social de una empresa.
     * Usarlo siempre, nunca concatenar nombre y apellido.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->kind === ClientKind::COMPANY
            ? (string) $this->business_name
            : trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * Nombre del saludo de los avisos: el nombre de pila o la razón social.
     */
    protected function greetingName(): Attribute
    {
        return Attribute::get(fn () => $this->kind === ClientKind::COMPANY
            ? (string) $this->business_name
            : (string) $this->first_name);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function clientNotifications(): HasMany
    {
        return $this->hasMany(ClientNotification::class);
    }
}
