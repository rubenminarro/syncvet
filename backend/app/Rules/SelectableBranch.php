<?php

namespace App\Rules;

use App\Http\Requests\UpdateCurrentBranchRequest;
use App\Models\Branch;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Una sucursal que el usuario puede elegir como actual: la regla de Branch::selectableBy
 * (activa y asignada; el scope de la clínica deja afuera las de otra). Entrega front-00,
 * revisión H8: la misma regla que me.branches y que AuthService::setCurrentBranch.
 */
class SelectableBranch implements ValidationRule
{
    public function __construct(private readonly ?User $user)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->user === null || ! is_scalar($value) || ! Branch::query()->selectableBy($this->user)->whereKey($value)->exists()) {
            $fail(UpdateCurrentBranchRequest::BRANCH_NOT_AVAILABLE);
        }
    }
}
