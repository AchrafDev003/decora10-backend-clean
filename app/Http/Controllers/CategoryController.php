<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Resources\CategoryResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use App\Http\Middleware;
use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;


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
    // ✏️ Crear categoría
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

        if ($request->hasFile('image')) {
            $cloudinary = new Cloudinary('cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io');

            $slugName = Str::slug($request->name);
            $publicId = "{$slugName}-" . uniqid(); // Sin "categories/"

            $result = $cloudinary->uploadApi()->upload(
                $request->file('image')->getRealPath(),
                [
                    'folder' => 'categories',
                    'public_id' => $publicId,
                    'overwrite' => true,
                    'resource_type' => 'image',
                ]
            );

            $categoryData['image'] = $result['secure_url'] ?? null;
        }

        $category = Category::create($categoryData);

        return response()->json([
            'message'  => 'Categoría creada correctamente',
            'category' => new CategoryResource($category)
        ], 201);
    }

// ✏️ Actualizar categoría
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

        if ($request->hasFile('image')) {
            $cloudinary = new Cloudinary('cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io');

            // Eliminar imagen anterior si existe
            if ($category->image) {
                $path = parse_url($category->image, PHP_URL_PATH);
                $filename = pathinfo($path, PATHINFO_FILENAME);
                (new UploadApi())->destroy("categories/{$filename}");
            }

            $slugName = Str::slug($request->name);
            $publicId = "{$slugName}-" . uniqid(); // Sin "categories/"

            $result = $cloudinary->uploadApi()->upload(
                $request->file('image')->getRealPath(),
                [
                    'folder' => 'categories',
                    'public_id' => $publicId,
                    'overwrite' => true,
                    'resource_type' => 'image',
                ]
            );

            $category->image = $result['secure_url'] ?? null;
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
