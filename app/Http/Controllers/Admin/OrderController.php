<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $ordersQuery = Order::query();
        if ($request->filled('username')) {
            $ordersQuery->whereHas('member', function($q) use ($request) {
                $q->where('username', 'like', '%' . $request->username . '%');
            });
        }
        $orders = $ordersQuery->paginate(30);
        return view('admin.orders.index', compact('orders'));
    }

    public function changeStatus($id)
    {
        $order = Order::find($id);
        $order->status = 'completed';
        $order->save();
        return redirect()->back();
    }
}
