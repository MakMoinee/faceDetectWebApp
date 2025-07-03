<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $baseURL = env('API_URL');
    return view('welcome', ['baseURL' => $baseURL]);
});

Route::get('/data_collect', function () {
    $baseURL = env('API_URL');
    return view('collect', ['baseURL' => $baseURL]);
});
