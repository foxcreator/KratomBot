<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subcategory;
use App\Models\Brand;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\SubcategoryStoreRequest;
use App\Http\Requests\Admin\SubcategoryUpdateRequest;

class SubcategoryController extends Controller
{
    public function index()
    {
        $subcategories = Subcategory::with('brand')->get();
        return view('admin.subcategories.index', compact('subcategories'));
    }

    public function create()
    {
        $brands = Brand::all();
        return view('admin.subcategories.create', compact('brands'));
    }

    public function store(SubcategoryStoreRequest $request)
    {
        $validated = $request->validated();
        Subcategory::create($validated);
        return redirect()->route('admin.subcategories.index')->with('success', 'Підкатегорію створено!');
    }

    public function edit(Subcategory $subcategory)
    {
        $brands = Brand::all();
        return view('admin.subcategories.edit', compact('subcategory', 'brands'));
    }

    public function update(SubcategoryUpdateRequest $request, Subcategory $subcategory)
    {
        $validated = $request->validated();
        $subcategory->update($validated);
        return redirect()->route('admin.subcategories.index')->with('success', 'Підкатегорію оновлено!');
    }

    public function destroy(Subcategory $subcategory)
    {
        $subcategory->delete();
        return redirect()->route('admin.subcategories.index')->with('success', 'Підкатегорію видалено!');
    }
} 