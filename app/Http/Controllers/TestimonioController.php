<?php

namespace App\Http\Controllers;

use App\Models\Testimonio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TestimonioController extends Controller
{
    /**
     * Mostrar solo testimonios publicados (para todos, incluso no logueados)
     */
    public function index()
    {
        $testimonios = Testimonio::with('user:id,name,email,photo')
            ->where('publicado', true)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $testimonios
        ], 200);
    }

    /**
     * Mostrar todos los testimonios (solo admin o dueño)
     */
    public function adminIndex()
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['admin', 'dueno'])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $testimonios = Testimonio::with('user:id,name,email,photo')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $testimonios
        ], 200);
    }

    /**
     * Crear un nuevo testimonio (siempre publicado = 0)
     */
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'nullable|string|max:255',
            'texto' => 'required|string|max:500',
            'rating' => 'required|numeric|min:1|max:5',
            'imagen' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Debes iniciar sesión.'], 403);
        }

        $imagenPath = $request->hasFile('imagen')
            ? $request->file('imagen')->store('testimonios', 'public')
            : ($user->photo ?? null);

        $testimonio = Testimonio::create([
            'user_id' => $user->id,
            'titulo' => $request->titulo ?? 'Experiencia',
            'texto' => $request->texto,
            'rating' => $request->rating,
            'imagen' => $imagenPath,
            'publicado' => false, // siempre 0
        ]);

        $testimonio->load('user:id,name,email,photo');

        return response()->json([
            'success' => true,
            'message' => 'Testimonio creado exitosamente.',
            'data' => $testimonio
        ], 201);
    }

    /**
     * Actualizar testimonio
     * - Cliente/cliente fiel: no puede cambiar publicado
     * - Admin/dueno: pueden cambiar todo
     */
    public function update(Request $request, $id)
    {
        $testimonio = Testimonio::findOrFail($id);
        $user = Auth::user();

        if ($testimonio->user_id !== $user->id && !in_array($user->role, ['admin', 'dueno'])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $request->validate([
            'titulo' => 'nullable|string|max:255',
            'texto' => 'required|string|max:500',
            'rating' => 'required|numeric|min:1|max:5',
            'imagen' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'publicado' => 'nullable|boolean',
        ]);

        $imagenPath = $testimonio->imagen;
        if ($request->hasFile('imagen')) {
            $imagenPath = $request->file('imagen')->store('testimonios', 'public');
        }

        // Solo admin o dueno puede cambiar publicado
        $publicado = in_array($user->role, ['admin', 'dueno'])
            ? $request->publicado ?? $testimonio->publicado
            : $testimonio->publicado;

        $testimonio->update([
            'titulo' => $request->titulo ?? $testimonio->titulo,
            'texto' => $request->texto,
            'rating' => $request->rating,
            'imagen' => $imagenPath,
            'publicado' => $publicado,
        ]);

        $testimonio->load('user:id,name,email,photo');

        return response()->json([
            'success' => true,
            'message' => 'Testimonio actualizado correctamente.',
            'data' => $testimonio
        ], 200);
    }

    /**
     * Eliminar testimonio
     */
    public function destroy($id)
    {
        $testimonio = Testimonio::findOrFail($id);
        $user = Auth::user();

        if ($testimonio->user_id !== $user->id && !in_array($user->role, ['admin', 'dueno'])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $testimonio->delete();

        return response()->json([
            'success' => true,
            'message' => 'Testimonio eliminado correctamente.'
        ], 200);
    }

    /**
     * Cambiar publicado (solo admin/dueno)
     */
    public function togglePublicado($id)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'dueno'])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $testimonio = Testimonio::findOrFail($id);
        $testimonio->publicado = !$testimonio->publicado;
        $testimonio->save();

        return response()->json([
            'success' => true,
            'publicado' => $testimonio->publicado
        ]);
    }
}
