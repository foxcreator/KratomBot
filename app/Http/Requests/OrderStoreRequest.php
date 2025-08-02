<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_phone' => ['required', 'string', 'max:20'],
            'shipping_city' => ['nullable', 'string', 'max:255'],
            'shipping_carrier' => ['nullable', 'string', 'max:255'],
            'shipping_office' => ['nullable', 'string', 'max:255'],

            'sale_type' => ['required', 'in:retail,wholesale'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_type' => ['nullable', 'in:cash,card,invoice'],
            'payment_receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:new,processing,completed,cancelled'],
            'total_amount' => ['nullable', 'numeric'],


            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.product_option_id' => ['required', 'exists:product_options,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
