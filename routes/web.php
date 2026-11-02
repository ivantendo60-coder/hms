<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RfidScanController;

Route::get('/rfid-scan', [RfidScanController::class, 'index'])->name('rfid.scan');
