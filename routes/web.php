<?php

use App\Immobile;
use App\Vertice;
use Illuminate\Support\Facades\Route;

Route::get('/', 'HomeController@index');

Route::get('init', 'HomeController@init');

Route::get('count', function () {
    $immobiles = Immobile::count();
    $vertices = Vertice::count();

    return [
        'immobiles' => number_format($immobiles, 0, ',', '.'),
        'vertices' => number_format($vertices, 0, ',', '.'),
    ];
});
