<?php

namespace App\Models;

use App\Enums\ServiceCategory;
use App\Enums\ServiceStatus;
use App\Enums\TaxRate;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'duration_minutes',
        'standard_price',
        'tax_rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'standard_price' => 'decimal:2',
            'tax_rate' => TaxRate::class,
            'status' => ServiceStatus::class,
        ];
    }
}
