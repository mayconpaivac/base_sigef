<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Immobile extends Model
{
    protected $fillable = [
        'code',
        'immobile',
    ];

    public function vertices()
    {
        return $this->hasMany(Vertice::class);
    }
}
