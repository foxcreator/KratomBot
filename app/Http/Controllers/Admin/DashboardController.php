<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index()
    {
        $monthlySales = DB::table('orders')
            ->where('status', 'completed')
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total_amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item, $index) use (&$previous) {
                static $previous = null;
                $current = $item->total;

                if ($previous === null) {
                    $item->change = 0;
                } elseif ($previous == 0) {
                    $item->change = 100;
                } else {
                    $item->change = round((($current - $previous) / $previous) * 100, 1);
                }

                $previous = $current;
                return $item;
            });

        if (!Auth::user()->isAdmin()) {
            return view('admin.orders.index');
        } else {
            return view('admin.dashboard.index', compact('monthlySales'));
        }
    }
}
