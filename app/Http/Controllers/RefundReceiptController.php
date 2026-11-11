<?php

namespace App\Http\Controllers;

use App\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RefundReceiptController extends Controller
{
    public function show(Request $request, SaleReturn $saleReturn): View
    {
        $user = Auth::user();
        abort_unless($user?->hasPermission('shifts.open') || $user?->hasPermission('shifts.view'), 403);

        $saleReturn->load(['lines.saleLine.product', 'lines.batch', 'sale', 'user', 'shift.till']);

        return view('sales.refund-receipt', [
            'refund' => $saleReturn,
        ]);
    }
}

