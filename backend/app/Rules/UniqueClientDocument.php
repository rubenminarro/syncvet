<?php

namespace App\Rules;

use App\Http\Requests\ClientMessages;
use App\Models\Client;
use App\Support\DocumentBase;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El documento de un cliente es único en la clínica por su número base (R-018):
 * "99999903", "099999903" y "99999903-6" son el mismo documento. La consulta va
 * por el modelo, con el scope de la clínica; los dados de baja tienen la base con
 * el sufijo y no chocan.
 */
class UniqueClientDocument implements ValidationRule
{
    public function __construct(private readonly ?Client $ignore = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $taken = Client::query()
            ->where('document_base', DocumentBase::of($value))
            ->when($this->ignore, fn ($query) => $query->whereKeyNot($this->ignore->getKey()))
            ->exists();

        if ($taken) {
            $fail(ClientMessages::all()['document_number']['unique']);
        }
    }
}
