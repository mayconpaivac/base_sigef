<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Vertice extends Model
{
    protected $fillable = [
        'immobile_id',
        'vertice',
        'sigma_x',
        'sigma_y',
        'sigma_z',
        'indice',
        'este',
        'norte',
        'altura',
    ];
}
