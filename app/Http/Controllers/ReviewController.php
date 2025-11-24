<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class ReviewController extends Controller
{
    // Aplicar autenticación a todas las rutas excepto ver reseñas
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    /**
     * Listar todas las reseñas de un producto
     */
    public function index(Request $request, $productId = null)
    {
        $query = Review::query()->with('user:id,name')->latest();

        // Filtrar por producto si no es "all"
        if ($productId && $productId !== 'all') {
            $query->where('product_id', $productId);
        }

        // Filtro por rating opcional
        if ($request->has('rating') && $request->rating !== '') {
            $query->where('rating', $request->rating);
        }

        // Búsqueda opcional por comentario o nombre de usuario
        if ($request->has('search') && trim($request->search) !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', function($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Paginación
        $perPage = $request->get('per_page', 10);
        $reviews = $query->paginate($perPage);

        return response()->json($reviews);
    }



    /**
     * Crear una reseña para un producto
     */
    public function store(Request $request, $productId)
    {
        // Validar existencia del producto
        $product = Product::findOrFail($productId);

        // Validar datos
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000',
        ]);

        $user = Auth::user();

        // Validar si ya hizo una reseña para el mismo producto
        $existingReview = Review::where('product_id', $productId)
            ->where('user_id', $user->id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'message' => 'Ya has enviado una reseña para este producto.',
            ], 409); // 409 Conflict
        }

        // Crear la reseña
        $review = Review::create([
            'product_id' => $productId,
            'rating'     => $request->rating,
            'comment'    => $request->comment,
            'user_id'    => $user->id,
        ]);

        return response()->json([
            'message' => 'Reseña enviada correctamente.',
            'review'  => $review,
        ], 201);
    }

    /**
     * Ver los detalles de una reseña
     */
    public function show($id)
    {
        $review = Review::with('user:id,name')->findOrFail($id);
        return response()->json($review);
    }

    // Actualizar una reseña (solo del dueño)


    public function update(Request $request, $id)
    {
        $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'sometimes|required|string',
        ]);

        $review = Review::findOrFail($id);
        $user = Auth::user();

        // ✅ Solo el autor o un admin/dueno puede modificar
        if ($user->id !== $review->user_id && !in_array($user->role, ['admin', 'dueno'])) {
            return response()->json(['message' => 'No tienes permiso para modificar esta reseña.'], 403);
        }

        $review->update($request->only(['rating', 'comment']));

        return response()->json($review);
    }


    /**
     * Eliminar una reseña (solo el usuario dueño puede)
     */
    public function destroy($id)
    {
        $review = Review::findOrFail($id);
        $user = Auth::user();

        // ✅ Solo el autor o un admin/dueno puede eliminar
        if ($user->id !== $review->user_id && !in_array($user->role, ['admin', 'dueno'])) {
            return response()->json(['message' => 'No tienes permiso para eliminar esta reseña.'], 403);
        }

        $review->delete();
        return response()->json(['message' => 'Reseña eliminada correctamente.'], 200);
    }
}
