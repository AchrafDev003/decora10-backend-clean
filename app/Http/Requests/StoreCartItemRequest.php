<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                // Si es product, validamos measure según las medidas definidas
                function ($attribute, $value, $fail) {
                    $type = $this->input('type');
                    $id = $this->input('id');

                    if ($type !== 'product') return;

                    $product = \App\Models\Product::find($id);
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
        ];
    }
}
