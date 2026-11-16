<?php

namespace App\Domain\Rewards\Models;

use Illuminate\Database\Eloquent\Model;

class RewardRule extends Model
{
    protected $primaryKey = 'rule';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'rule',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }
}
