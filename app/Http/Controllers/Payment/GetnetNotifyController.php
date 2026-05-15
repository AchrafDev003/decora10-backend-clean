<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderStatusHistory;

class GetnetNotifyController extends Controller
{
    public function notify(Request $request)
    {
        Log::info('🔥 NOTIFY HIT', [
            'input' => $request->all(),
        ]);

        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }
}
