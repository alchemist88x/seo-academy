<?php

namespace App\Domain\AI\Models;

use App\Domain\Tenants\Concerns\BelongsToAcademy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AcademyAiCredential extends Model
{
    use BelongsToAcademy, HasUuids;

    protected $fillable = [
        'academy_id',
        'driver',
        'endpoint',
        'model',
        'api_key',
        'is_active',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
