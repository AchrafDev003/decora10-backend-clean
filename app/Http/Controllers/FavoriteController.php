<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    // GET /favorites
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no logueado'
            ], 401);
        }

        // Traemos favoritos con producto e imágenes del producto
        $favorites = $user->favorites()->with(['product.images'])->get();

        return response()->json([
            'success' => true,
            'data' => $favorites
        ]);
    }


    // POST /favorites
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no logueado'
            ], 401);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        // Evitar duplicados
        $exists = Favorite::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'El producto ya está en favoritos'
            ], 409);
        }

        $favorite = Favorite::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Producto añadido a favoritos',
            'data' => $favorite
        ], 201);
    }

    // DELETE /favorites/{productId}
    public function destroy(Request $request, $productId)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no logueado'
            ], 401);
        }

        $favorite = Favorite::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if (!$favorite) {
            return response()->json([
                'success' => false,
                'message' => 'Favorito no encontrado'
            ], 404);
        }

        $favorite->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado de favoritos'
        ], 200);
    }
}
