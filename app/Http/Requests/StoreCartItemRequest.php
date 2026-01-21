<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|integer', // puede ser product o pack
            'type' => 'required|in:product,pack',
            'quantity' => 'required|integer|min:1|max:5',

        ];
    }
}
