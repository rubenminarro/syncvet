<?php

namespace App\Models;

use App\Enums\DocumentTemplateType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    use Auditable;

    protected $fillable = [
        'type',
        'name',
        'content',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentTemplateType::class,
            'is_active' => 'boolean',
        ];
    }
}
