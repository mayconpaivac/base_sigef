<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', 'HomeController@index');

Route::get('init', 'HomeController@init');

Route::get('shape', 'HomeController@shape');

Route::get('teste', 'TesteController@index');
Route::get('aff', 'TesteController@aff');

Route::get('teste2', function () {
    return $response = Http::withoutVerifying()
        ->withCookies([
            'sessionid' => 'adf80e87e46fca3d8e7c83bb9d10ae73'
        ], 'sigef.incra.gov.br')
        ->withOptions([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36',
            ]
        ])
        ->get('https://sigef.incra.gov.br/geo/exportar/parcela/csv/4c6897b0-d8ca-47da-9149-fcad117fe266');
});
