<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            // MÉTODO DE PAGO
            'payment_method' => [
                'required',
                'string',
                Rule::in(['paypal', 'card', 'cash', 'bizum'])
            ],

            // CUPÓN
            'promo_code' => 'nullable|string|max:50',


            // DIRECCIÓN
            'line1'          => 'required|string|max:255',
            'line2'          => 'nullable|string|max:255',
            'city'           => 'required|string|max:100',
            'zipcode'        => 'nullable|string|max:20',
            'country'        => 'required|string|max:100',

            // TELÉFONOS
            'mobile1'        => 'required|string|max:20',
            'mobile2'        => 'nullable|string|max:20',

            // ENTREGA
            'type'           => 'required|string|in:domicilio,local',
        ];
    }

    public function messages()
    {
        return [
            'payment_method.required' => 'El método de pago es obligatorio.',
            'payment_method.in'       => 'Método de pago no válido.',

            'promo_code.exists'       => 'Este cupón no existe.',

            'line1.required'          => 'La dirección principal (línea 1) es obligatoria.',
            'city.required'           => 'La ciudad es obligatoria.',
            'country.required'        => 'El país es obligatorio.',
            'mobile1.required'        => 'El teléfono principal es obligatorio.',

            'type.required'           => 'Debes indicar el tipo de entrega.',
            'type.in'                 => 'El tipo de entrega debe ser domicilio o local.',
        ];
    }
}
