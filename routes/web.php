<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::any('/', function (): RedirectResponse {
    return redirect(route('scramble.docs.ui'));
});
