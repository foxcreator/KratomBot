<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->input('from_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $toDate = $request->input('to_date', Carbon::now()->format('Y-m-d'));

        $query = OrderItem::query()
            ->with('product')
            ->whereBetween('created_at', [
                $fromDate . ' 00:00:00',
                $toDate . ' 23:59:59',
            ]);

        $orderItems = $query
            ->select(
                'product_id',
                DB::raw('AVG(price) as price'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(quantity * price) as total_sum')
            )
            ->groupBy('product_id')
            ->get();

        return view('admin.reports.index', compact('orderItems', 'fromDate', 'toDate'));
    }
}
