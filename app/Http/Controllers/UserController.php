<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // ============================
    // 🔹 GET /api/v1/users
    // ============================
    public function index()
    {
        $users = User::paginate(10);
        return response()->json($users);
    }

    // ============================
    // 🔹 POST /api/v1/users
    // ============================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|string|min:6',
            'role'        => 'nullable|in:admin,dueno,cliente,cliente_fiel',
            'provider'    => 'nullable|string|max:50',       // Nuevo
            'provider_id' => 'nullable|string|max:255',      // Nuevo
        ]);

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => Hash::make($validated['password']),
            'role'        => $validated['role'] ?? 'cliente',
            'provider'    => $validated['provider'] ?? 'local', // "local" por defecto
            'provider_id' => $validated['provider_id'] ?? null,
        ]);

        return response()->json($user, 201);
    }

    // ============================
    // 🔹 GET /api/v1/users/{id}
    // ============================
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    // ============================
    // 🔹 PUT /api/v1/users/{id}
    // ============================
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Solo admin/dueno pueden editar a otros; usuario normal solo a sí mismo
        if (!in_array(auth()->user()->role, ['admin', 'dueno']) && auth()->id() !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Nadie que no sea admin puede tocar perfiles admin
        if ($user->role === 'admin' && auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado para cambiar perfil admin'], 403);
        }

        $validated = $request->validate([
            'name'     => 'sometimes|required|string|max:100',
            'email'    => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role'     => 'sometimes|required|in:admin,dueno,cliente,cliente_fiel',
            'photo'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'provider'    => 'nullable|string|max:50',   // Nuevo
            'provider_id' => 'nullable|string|max:255',  // Nuevo
        ]);

        // ⚠️ Evitar que usuarios con provider=google cambien su email o contraseña
        if ($user->provider === 'google') {
            unset($validated['email']);
            unset($validated['password']);
        }

        // No permitir que no-admin suba a admin
        if (isset($validated['role']) && auth()->user()->role !== 'admin' && $validated['role'] === 'admin') {
            return response()->json(['message' => 'No autorizado para asignar rol admin'], 403);
        }

        // Hashear la contraseña si viene
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Manejar la foto
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }

            $nameSlug = str_replace(' ', '_', strtolower($user->name));
            $extension = $request->file('photo')->getClientOriginalExtension();
            $filename = $nameSlug . '-' . $user->id . '.' . $extension;

            $validated['photo'] = $request->file('photo')->storeAs('photos/users', $filename, 'public');
        }

        $user->update($validated);

        return response()->json($user, 200);
    }

    // ============================
    // 🔹 PATCH /users/{id}/photo
    // ============================
    public function updateUserPhoto(Request $request, $id)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = User::findOrFail($id);

        if (auth()->id() !== $user->id && !in_array(auth()->user()->role, ['admin', 'dueno'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }

            $nameSlug = str_replace(' ', '_', strtolower($user->name));
            $extension = $request->file('photo')->getClientOriginalExtension();
            $filename = $nameSlug . '-' . $user->id . '.' . $extension;

            $path = $request->file('photo')->storeAs('photos/users', $filename, 'public');

            $user->photo = $path;
            $user->save();

            return response()->json([
                'success' => true,
                'photo' => $user->photo,
                'message' => 'Foto actualizada correctamente'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se subió ninguna foto válida'
        ], 422);
    }

    // ============================
    // 🔹 DELETE /api/v1/users/{id}
    // ============================
    public function destroy($id)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        // Admin puede eliminar cualquiera
        if ($currentUser->role === 'admin') {
            $user->delete();
            return response()->json(['message' => 'Usuario eliminado correctamente']);
        }

        // Dueño puede eliminar solo clientes normales o fieles
        if ($currentUser->role === 'dueno') {
            if (in_array($user->role, ['cliente', 'cliente_fiel'])) {
                $user->delete();
                return response()->json(['message' => 'Usuario eliminado correctamente']);
            } else {
                return response()->json(['message' => 'No autorizado para eliminar este usuario'], 403);
            }
        }

        return response()->json(['message' => 'No autorizado para eliminar usuarios'], 403);
    }
}
