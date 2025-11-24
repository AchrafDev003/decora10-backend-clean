<?php

namespace App\Http\Requests;
use Illuminate\Validation\Rule;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize()
    {
        return true; // ya estás controlando permisos con middleware
    }

    public function rules()
    {
        $productId = $this->route('product')?->id; // captura el ID de la ruta si existe

        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0',
            'is_promo' => 'nullable|boolean',
            'promo_ends_at' => 'nullable|date|after_or_equal:today',
            'quantity' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:2048',

            'category_id' => 'required|exists:categories,id',
            'id_product' => [
                'required',
                'string',
                Rule::unique('products', 'id_product')->ignore($productId),
            ],
        ];
    }

}
