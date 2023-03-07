<?php

use App\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;

Route::get('/', 'HomeController@index');

Route::post('/file', 'ProcessFormController@file')->name('processFile');

Route::get('init', 'HomeController@init');

Route::get('shape', 'HomeController@shape');


Route::get('/delete-batch', function () {
    Batch::query()->delete();

    return 'ok';
});

Route::get('/batch', function () {
    $batch = Batch::query()->latest()->first();

    if (!$batch) {
        return [
            'model' => [],
            'batch' => [],
        ];
    }

    return [
        'model' => $batch,
        'batch' => Bus::findBatch($batch->batch_id),
    ];
});
