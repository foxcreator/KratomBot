<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Brand;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('brand')->get();
        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $brands = Brand::all();
        return view('admin.products.create', compact('brands'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand_id' => 'required|exists:brands,id',
            'price' => 'required|numeric',
            'description' => 'required|string',
            'image' => 'nullable|image|max:2048',
            'is_top_sales' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        $product = Product::create($validated);

        // Зберігаємо варіанти товару
        if ($request->has('options')) {
            foreach ($request->options as $option) {
                if (!empty($option['name']) && !empty($option['price'])) {
                    $product->options()->create([
                        'name' => $option['name'],
                        'price' => $option['price'],
                    ]);
                }
            }
        }

        return redirect()->route('admin.products.index')->with('success', 'Продукт створено!');
    }

    public function edit(Product $product)
    {
        $brands = Brand::all();
        return view('admin.products.edit', compact('product', 'brands'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand_id' => 'required|exists:brands,id',
            'price' => 'required|numeric',
            'description' => 'required|string',
            'image' => 'nullable|image|max:2048',
            'is_top_sales' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = '/storage/' . $path;
        } else {
            $validated['image_url'] = $product->image_url;
        }

        $product->update($validated);

        // Оновлюємо/додаємо/видаляємо варіанти товару
        $optionIds = [];
        if ($request->has('options')) {
            foreach ($request->options as $key => $option) {
                if (!empty($option['name']) && !empty($option['price'])) {
                    if (isset($option['id'])) {
                        // update
                        $productOption = $product->options()->where('id', $option['id'])->first();
                        if ($productOption) {
                            $productOption->update([
                                'name' => $option['name'],
                                'price' => $option['price'],
                            ]);
                            $optionIds[] = $productOption->id;
                        }
                    } else {
                        // create
                        $newOption = $product->options()->create([
                            'name' => $option['name'],
                            'price' => $option['price'],
                        ]);
                        $optionIds[] = $newOption->id;
                    }
                }
            }
        }
        // Видаляємо ті варіанти, яких немає у формі
        $product->options()->whereNotIn('id', $optionIds)->delete();

        return redirect()->route('admin.products.index')->with('success', 'Продукт оновлено!');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Продукт видалено!');
    }
} 
