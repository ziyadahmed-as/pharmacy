<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredentialReveal extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret'];

    protected $casts = ['expires_at' => 'datetime'];
}
