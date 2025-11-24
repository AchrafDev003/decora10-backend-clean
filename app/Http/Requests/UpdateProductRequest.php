<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'name' => 'required|string|max:255',
            //'slug' => "required|string|unique:products,slug,$id",
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0',
            'is_promo' => 'nullable|boolean',
            'promo_ends_at' => 'nullable|date|after_or_equal:today',
            'quantity' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'category_id' => 'required|exists:categories,id',
            'id_product' => "required|string|unique:products,id_product,$id",
        ];
    }
}
