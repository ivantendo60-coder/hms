<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/rfid-scan', function () {
    $legacyUrl = rtrim((string) config('app.legacy_url'), '/');

    return redirect()->away($legacyUrl . '/pages/scan.php');
})->name('rfid.scan');
