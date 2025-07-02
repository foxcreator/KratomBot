<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Brand;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;

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
        $subcategories = \App\Models\Subcategory::all();
        return view('admin.products.create', compact('brands', 'subcategories'));
    }

    public function store(ProductStoreRequest $request)
    {
        $validated = $request->validated();
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }
        Product::create($validated);
        return redirect()->route('admin.products.index')->with('success', 'Продукт створено!');
    }

    public function edit(Product $product)
    {
        $brands = Brand::all();
        $subcategories = \App\Models\Subcategory::all();
        return view('admin.products.edit', compact('product', 'brands', 'subcategories'));
    }

    public function update(ProductUpdateRequest $request, Product $product)
    {
        $validated = $request->validated();
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = '/storage/' . $path;
        } else {
            $validated['image_url'] = $product->image_url;
        }
        $product->update($validated);
        return redirect()->route('admin.products.index')->with('success', 'Продукт оновлено!');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Продукт видалено!');
    }
} 
