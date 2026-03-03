<?php

use Illuminate\Support\Facades\Route;

Route::get('/client-info', function () {
    return response()->json(config('client'));
});

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
