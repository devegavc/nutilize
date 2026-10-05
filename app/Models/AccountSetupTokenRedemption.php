<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountSetupTokenRedemption extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'token_hash',
        'consumed_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
        ];
    }
}
