<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'id_product' => [
                'required',
                'string',
                Rule::unique('products', 'id_product')->ignore($id),
            ],

            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0',
            'is_promo' => 'nullable|boolean',
            'promo_ends_at' => 'nullable|date|after_or_equal:today',
            'quantity' => 'required|integer|min:1',
            'category_id' => 'required|exists:categories,id',

            // ✅ CLAVE
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096',

            // 🔥 Para borrado
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'integer|exists:product_images,id',
        ];
    }
}
