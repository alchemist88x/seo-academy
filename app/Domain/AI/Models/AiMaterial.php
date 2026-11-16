<?php

namespace App\Domain\AI\Models;

use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiMaterial extends Model
{
    use BelongsToAcademy, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'academy_id',
        'filename',
        'stored_path',
        'extracted_text',
        'uploaded_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
