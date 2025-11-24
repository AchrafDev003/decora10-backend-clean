<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize()
    {
        // Solo usuarios autenticados pueden actualizar su pedido
        return auth()->check();
    }

    public function rules()
    {
        return [
            'status'                  => 'required|string|in:pendiente,procesando,enviado,en_ruta,entregado,cancelado',
            'nota'                    => 'nullable|string|max:255',

            // Campos opcionales para admin
            'shipping_address'        => 'string|nullable|max:255',
            'mobile1'                 => 'string|nullable|max:20',
            'mobile2'                 => 'string|nullable|max:20',
            'courier'                 => 'string|nullable|max:50',
            'tracking_number'         => 'string|nullable|max:50',
            'estimated_delivery_date' => 'date|nullable',
            'address_id'              => 'exists:addresses,id|nullable',
            'payment_method'          => 'string|nullable|max:50',
        ];
    }

    public function messages()
    {
        return [
            'status.required' => 'El estado del pedido es obligatorio.',
            'status.in'       => 'Estado inválido. Valores permitidos: pendiente, procesando, enviado, en_ruta, entregado, cancelado.',
            'address_id.exists'=> 'La dirección seleccionada no existe.',
        ];
    }
}
