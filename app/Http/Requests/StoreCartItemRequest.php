<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Product;

class StoreCartItemRequest extends FormRequest
{
    private const VALID_MEASURES = ['90x190', '135x190', '150x190'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|integer', // puede ser product o pack
            'type' => ['required', Rule::in(['product','pack'])],
            'quantity' => 'required|integer|min:1|max:5',
            'measure' => [
                function ($attribute, $value, $fail) {
                    $type = $this->input('type');
                    $id = $this->input('id');

                    if ($type !== 'product') return;

                    $product = Product::find($id);
                    if (!$product) {
                        $fail("Producto no encontrado.");
                        return;
                    }

                    // Solo si la categoría es colchones (id = 76)
                    if ($product->category?->id === 76) {
                        if (!$value) {
                            $fail("La medida es obligatoria para colchones.");
                            return;
                        }

                        if (!in_array($value, self::VALID_MEASURES)) {
                            $fail("Medida inválida. Las válidas son: " . implode(', ', self::VALID_MEASURES));
                        }
                    }
                }
            ],
            // ✅ Validamos que el frontend envíe el precio calculado
            'price' => 'required|numeric|min:0',
            'promo_price' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'El producto es obligatorio.',
            'id.integer' => 'ID del producto inválido.',
            'type.required' => 'El tipo es obligatorio.',
            'type.in' => 'El tipo debe ser "product" o "pack".',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número.',
            'quantity.min' => 'La cantidad mínima es 1.',
            'quantity.max' => 'La cantidad máxima es 5.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un número.',
            'promo_price.required' => 'El precio promocional es obligatorio.',
            'promo_price.numeric' => 'El precio promocional debe ser un número.',
        ];
    }
}
