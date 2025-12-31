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
        $id = $this->route('id'); // ID del producto en la ruta

        return [
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'price'         => 'required|numeric|min:0',
            'promo_price'   => 'nullable|numeric|min:0',
            'is_promo'      => 'nullable|boolean',
            'promo_ends_at' => 'nullable|date|after_or_equal:today',
            'quantity'      => 'required|integer|min:1',

            'image'         => 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:2048',
            'images'        => 'nullable|array',
            'images.*'      => 'image|mimes:jpg,jpeg,png,webp,avif|max:2048',

            'category_id'   => 'required|exists:categories,id',

            'id_product' => [
                'required',
                'string',
                Rule::unique('products', 'id_product')->ignore($id),
            ],

            // 👇 NUEVO
            'logistic_type' => [
                'required',
                Rule::in(['small', 'medium', 'heavy']),
            ],
        ];
    }
}
