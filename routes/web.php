<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/client', 'client');
Route::view('/docs', 'api-docs');
