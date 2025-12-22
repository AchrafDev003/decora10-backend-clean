<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Cloudinary\Cloudinary;

use Illuminate\Support\Str;
use App\Models\User;


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

        $validated = $request->validate([
            'name'  => 'sometimes|required|string|max:100',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'role'  => 'sometimes|required|in:admin,dueno,cliente,cliente_fiel',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Evitar asignar rol admin si no eres admin
        if (isset($validated['role']) && auth()->user()->role !== 'admin' && $validated['role'] === 'admin') {
            return response()->json(['message' => 'No autorizado para asignar rol admin'], 403);
        }

        // Subir foto a Cloudinary
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $cloudinary = new Cloudinary('cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io');

            $slugName = Str::slug($user->name);
            $publicId = "{$slugName}-{$user->id}";

            $result = $cloudinary->uploadApi()->upload($request->file('photo')->getRealPath(), [
                'folder' => 'users',
                'public_id' => $publicId,
                'resource_type' => 'image',
            ]);

            $validated['photo'] = $result['secure_url'] ?? null;
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

        // Permisos: solo el propio usuario, admin o dueño
        if (
            auth()->id() !== $user->id &&
            !in_array(auth()->user()->role, ['admin', 'dueno'])
        ) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        try {
            $cloudinary = new Cloudinary('cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io');

            // 🔹 Eliminar foto anterior si existe en Cloudinary
            if ($user->photo && str_contains($user->photo, 'res.cloudinary.com')) {
                $path = parse_url($user->photo, PHP_URL_PATH);
                $filename = pathinfo($path, PATHINFO_FILENAME);

                if ($filename) {
                    (new UploadApi())->destroy("users/{$filename}");
                }
            }

            // 🔹 Nombre exacto para Cloudinary
            $slugName = Str::slug($user->name);
            $publicId = "{$slugName}-{$user->id}";

            // 🔹 Subida a Cloudinary
            $result = $cloudinary->uploadApi()->upload(
                $request->file('photo')->getRealPath(),
                [
                    'folder' => 'users',
                    'public_id' => $publicId,
                    'overwrite' => true,
                    'resource_type' => 'image',
                ]
            );

            $user->photo = $result['secure_url'] ?? null;
            $user->save();

            return response()->json([
                'success' => true,
                'photo' => $user->photo,
                'message' => 'Foto actualizada correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir la imagen',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ============================
    // 🔹 DELETE /api/v1/users/{id}
    // ============================
    public function destroy($id)
    {
        $currentUser = auth()->user();
        $user = User::findOrFail($id);

        // 🔐 Permisos: admin o dueño (solo clientes)
        if (
            $currentUser->role !== 'admin' &&
            !(
                $currentUser->role === 'dueno' &&
                in_array($user->role, ['cliente', 'cliente_fiel'])
            )
        ) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        try {
            // 🔧 Cloudinary IGUAL que en updateUserPhoto
            $cloudinary = new Cloudinary(
                'cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io'
            );

            // 🔥 Eliminar foto en Cloudinary si existe
            if ($user->photo && str_contains($user->photo, 'res.cloudinary.com')) {

                $path = parse_url($user->photo, PHP_URL_PATH);
                $filename = pathinfo($path, PATHINFO_FILENAME);

                if ($filename) {
                    $cloudinary->uploadApi()->destroy("users/{$filename}");
                }
            }

            // 🗑️ Eliminar usuario
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado correctamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el usuario',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
