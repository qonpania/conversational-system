<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin', 302);

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/voice', function () {
    return view('voice-call', ['apiKey' => config('services.google.api_key')]);
});
