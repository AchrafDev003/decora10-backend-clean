<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Resources\CategoryResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use App\Http\Middleware;

class CategoryController extends Controller
{
    public function __construct()
    {
        // El middleware de auth solo se aplica a store, update, destroy
        $this->middleware('auth:sanctum')->only(['store', 'update', 'destroy']);
        $this->middleware(\App\Http\Middleware\CheckUserRole::class . ':admin,dueno')->only(['store', 'update', 'destroy']);
    }



    // 🔍 Listar categorías públicas
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('name')->get();
        return response()->json(CategoryResource::collection($categories));
    }

    // ➕ Crear categoría (admin o dueño)
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string|max:1000',
            'image'       => 'nullable|image|max:2048', // máximo 2MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $categoryData = [
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
        ];

        // Si hay imagen, guardarla en storage y guardar el path
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
            $categoryData['image'] = $imagePath;
        }

        $category = Category::create($categoryData);

        return response()->json([
            'message'  => 'Categoría creada correctamente',
            'category' => new CategoryResource($category)
        ], 201);
    }

// ✏️ Actualizar categoría (admin o dueño)
    public function update(Request $request, $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string|max:1000',
            'image'       => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category->name = $request->name;
        $category->slug = Str::slug($request->name);
        $category->description = $request->description;

        // Guardar nueva imagen si se sube
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
            $category->image = $imagePath;
        }

        $category->save();

        return response()->json([
            'message'  => 'Categoría actualizada con éxito',
            'category' => new CategoryResource($category)
        ]);
    }


    // 🗑️ Eliminar categoría
    public function destroy($id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Categoría eliminada con éxito']);
    }
}
