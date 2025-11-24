<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses;
        return response()->json($addresses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'line1' => 'required|string',
            'line2' => 'nullable|string',
            'city' => 'required|string',
            'zipcode' => 'nullable|string', // ahora opcional
            'country' => 'required|string',
            'mobile1' => 'required|string',
            'mobile2' => 'nullable|string',
            'is_default' => 'boolean'
        ]);

        if (!empty($validated['is_default']) && $validated['is_default']) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($validated);
        return response()->json($address, 201);
    }

    public function update(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $validated = $request->validate([
            'type' => 'required|string',
            'line1' => 'required|string',
            'line2' => 'nullable|string',
            'city' => 'required|string',
            'zipcode' => 'nullable|string', // ahora opcional
            'country' => 'required|string',
            'mobile1' => 'required|string',
            'mobile2' => 'nullable|string',
            'is_default' => 'boolean'
        ]);

        if (!empty($validated['is_default']) && $validated['is_default']) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address->update($validated);
        return response()->json($address);
    }

    public function destroy(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();
        return response()->json(['message' => 'Address deleted.']);
    }

    // Obtener direcciones de un usuario específico (admin/dueno)
    public function getUserAddresses(User $user)
    {
        return response()->json($user->addresses);
    }

    // Actualizar una dirección como admin o dueño
    public function adminUpdate(Request $request, Address $address)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'line1' => 'required|string',
            'line2' => 'nullable|string',
            'city' => 'required|string',
            'zipcode' => 'nullable|string', // ahora opcional
            'country' => 'required|string',
            'mobile1' => 'required|string',
            'mobile2' => 'nullable|string',
            'is_default' => 'boolean'
        ]);

        if (!empty($validated['is_default']) && $validated['is_default']) {
            $address->user->addresses()->update(['is_default' => false]);
        }

        $address->update($validated);
        return response()->json($address);
    }

    // Eliminar dirección como admin o dueño
    public function adminDestroy(Address $address)
    {
        $address->delete();
        return response()->json(['message' => 'Address deleted by admin/dueno.']);
    }

    private function authorizeAddress(Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            abort(403, 'No autorizado.');
        }
    }
}
