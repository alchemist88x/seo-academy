<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BackupRecord extends Model
{
    use HasUuids;

    protected $table = 'backups';

    protected $fillable = [
        'filename',
        'disk',
        'type',
        'database_driver',
        'size_bytes',
        'status',
        'checksum',
        'metadata',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
