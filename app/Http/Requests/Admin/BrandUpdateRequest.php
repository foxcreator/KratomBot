<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BrandUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $brandId = $this->route('brand')?->id ?? null;
        return [
            'name' => 'required|string|max:255|unique:brands,name,' . $brandId,
            'description' => 'nullable|string',
            'price' => 'nullable|string',
        ];
    }
} 