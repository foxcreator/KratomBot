<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderStoreRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

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

    public function create()
    {
        $products = Product::all();
        $productOptions = ProductOption::all();
        return view('admin.orders.create', compact('products', 'productOptions'));
    }

    public function store(OrderStoreRequest $request)
    {
        $totalAmount = 0;
        foreach ($request->input('products') as $productData) {
            $totalAmount += $productData['price'] * $productData['quantity'];
        }

        $data = $request->validated();

        DB::beginTransaction();
        try {
            $order = Order::create([
                'shipping_name' => $data['shipping_name'],
                'shipping_phone' => $data['shipping_phone'],
                'shipping_city' => $data['shipping_city'],
                'shipping_carrier' => $data['shipping_carrier'],
                'shipping_office' => $data['shipping_office'],
                'sale_type' => $data['sale_type'],
                'discount_percent' => $data['discount_percent'],
                'payment_type' => $data['payment_type'],
                'notes' => $data['notes'],
                'status' => $data['status'],
                'total_amount' => $totalAmount,
            ]);

            if ($order) {
                foreach ($request->input('products') as $productData) {
                    $order->orderItems()->create([
                        'product_id' => $productData['product_id'],
                        'product_option_id' => $productData['product_option_id'],
                        'quantity' => $productData['quantity'],
                        'price' => $productData['price'],
                    ]);
                }

                if ($request->hasFile('payment_receipt')) {
                    $order->addMediaFromRequest('payment_receipt')->toMediaCollection('receipts');
                }
                DB::commit();
                return redirect()->route('admin.orders.index')->with('status', 'Замовлення створено');
            }
        }catch (\Exception $exception){
            DB::rollBack();
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back();
    }
}
