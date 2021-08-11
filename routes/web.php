<?php

use Illuminate\Support\Facades\Route;

Route::get('/', 'HomeController@index');

Route::get('init', 'HomeController@init');

Route::get('shape', 'HomeController@shape');

Route::get('teste', 'TesteController@index');
Route::get('aff', 'TesteController@aff');

Route::get('teste2', function () {
    $bo = file_get_contents(storage_path('app/bo.txt'));

    $bos = explode("\n", $bo);

    foreach ($bos as $bo) {
        $code = trim($bo);
        if (!file_exists(storage_path('app/13/' . $code . '_p.csv'))) {
            $parcela = file_get_contents('https://sigef.incra.gov.br/geo/exportar/parcela/csv/' . trim($bo) . '/');
            file_put_contents(storage_path('app/13/' . trim($bo) . '_p.csv'), $parcela);
        }

        if (!file_exists(storage_path('app/13/' . $code . '_v.csv'))) {
            $vertices = file_get_contents('https://sigef.incra.gov.br/geo/exportar/vertice/csv/' . trim($bo) . '/');
            file_put_contents(storage_path('app/13/' . trim($bo) . '_v.csv'), $vertices);
        }
    }
});
