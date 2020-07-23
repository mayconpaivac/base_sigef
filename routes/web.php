<?php

use Illuminate\Support\Facades\Route;

Route::get('/', 'HomeController@index');

Route::get('init', 'HomeController@init');

Route::get('shape', 'HomeController@shape');
