<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Models\Setting;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['member', 'orderItems.product'])
                      ->orderBy('created_at', 'desc')
                      ->paginate(20);

        return view('admin.orders.index', compact('orders'));
    }

    public function show($id)
    {
        $order = Order::with(['member', 'orderItems.product', 'orderItems.productOption'])->findOrFail($id);
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        return view('admin.orders.show', compact('order', 'settings'));
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);
        
        return redirect()->back()->with('success', 'Статус замовлення оновлено');
    }

    public function updateNotes(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->update(['notes' => $request->notes]);
        
        return redirect()->back()->with('success', 'Примітки оновлено');
    }
}
