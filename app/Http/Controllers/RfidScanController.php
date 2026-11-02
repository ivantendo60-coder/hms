<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RfidScanController extends Controller
{
    public function index()
    {
        return view('scan', [
            'pageTitle' => 'RFID Scan',
            'knownRedirect' => url('/pages/patient/view.php'),
            'unknownRedirect' => url('/pages/receptionist/confirm_rfid.php'),
        ]);
    }
}
