<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Product;

class StoreProductRequest extends FormRequest
{
    public function authorize()
    {
        return true; // permisos gestionados por middleware
    }

    public function rules()
    {
        $productId = $this->route('product')?->id;

        return [
            // Identidad
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',

            // Precios
            'price' => 'required|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0',
            'is_promo' => 'nullable|boolean',
            'promo_ends_at' => 'nullable|date|after_or_equal:today',

            // Stock
            'quantity' => 'required|integer|min:1',

            // 🔥 LOGÍSTICA
            'logistic_type' => [
                'nullable',
                Rule::in([
                    Product::LOGISTIC_SMALL,
                    Product::LOGISTIC_MEDIUM,
                    Product::LOGISTIC_HEAVY,
                ]),
            ],

            // Imágenes
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp,avif|max:2048',

            // Relaciones
            'category_id' => 'required|exists:categories,id',

            // Código interno
            'id_product' => [
                'required',
                'string',
                Rule::unique('products', 'id_product')->ignore($productId),
            ],
        ];
    }

    /**
     * Valores por defecto normalizados
     */
    protected function prepareForValidation()
    {
        if (!$this->has('logistic_type')) {
            $this->merge([
                'logistic_type' => Product::LOGISTIC_SMALL,
            ]);
        }
    }
}
