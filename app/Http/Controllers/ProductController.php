<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Str;
use App\Http\Resources\ProductResource;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store', 'update', 'destroy']);
        $this->middleware(\App\Http\Middleware\CheckUserRole::class . ':admin,dueno')
            ->only(['store', 'update', 'destroy']);
    }

    // -----------------------------
    // --- Slug único automático ---
    // -----------------------------
    private function generateUniqueSlug($name, $productId = null)
    {
        // 1. Convertimos el nombre en slug
        $slug = Str::slug($name);
        $originalSlug = $slug;

        // 2. Contador inicial
        $counter = 1;

        // 3. Mientras exista un producto con ese slug...
        while (
        Product::where('slug', $slug)
            ->when($productId, fn($q) => $q->where('id', '!=', $productId))
            ->exists()
        ) {
            // ...añadimos un número incremental
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        // 4. Devolvemos el slug único
        return $slug;
    }


    // -----------------------------
    // --- Listado de productos ---
    // -----------------------------
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 16);
        $page = $request->input('page', 1);

        $query = Product::with(['category', 'images']);

        // 🔎 Búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // 📂 Filtrar por categoría
        if ($request->filled('category') && $request->category !== 'Todas') {
            $categoryId = $request->category;
            $query->where('category_id', $categoryId);
        }


        // ✅ Filtrar solo productos en promoción
        if ($request->filled('is_promo')) {
            $isPromo = filter_var($request->is_promo, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_promo', $isPromo);
        }

        // ↕️ Ordenar
        if ($request->filled('sort')) {
            match ($request->sort) {
                'precio_asc'  => $query->orderBy('price', 'asc'),
                'precio_desc' => $query->orderBy('price', 'desc'),
                'nombre_asc'  => $query->orderBy('name', 'asc'),
                'nombre_desc' => $query->orderBy('name', 'desc'),
                default       => $query->latest(),
            };
        } else {
            $query->latest();
        }

        // 🔹 Paginación
        $products = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data'    => ProductResource::collection($products),
            'current_page' => $products->currentPage(),
            'last_page'    => $products->lastPage(),
            'per_page'     => $products->perPage(),
            'total'        => $products->total(),
        ]);

    }

    // -----------------------------
// --- 4 productos por categoría (excepto Colchonería) ---
// -----------------------------
    public function getFeaturedByCategory()
    {
        $categories = \App\Models\Category::where('id', '!=', 76)->get();
        $result = [];

        foreach ($categories as $category) {
            $products = Product::with('images')
                ->where('category_id', $category->id)
                ->inRandomOrder()
                ->take(4)
                ->get();

            if ($products->isNotEmpty()) {
                $result[] = [
                    'category' => $category->name,
                    'category_id' => $category->id,
                    'products' => ProductResource::collection($products),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }



// -----------------------------
// --- Productos de Colchonería (id=76): Flex, Dupen, Biolife ---
// -----------------------------
    public function getColchoneriaHighlights()
    {
        $keywords = ['Flex', 'Dupen', 'Biolife'];
        $categoryId = 76;
        $result = [];

        foreach ($keywords as $word) {
            $products = Product::with('images')
                ->where('category_id', $categoryId)
                ->where('name', 'LIKE', "%{$word}%")
                ->inRandomOrder()
                ->take(4)
                ->get();

            if ($products->isNotEmpty()) {
                $result[] = [
                    'brand' => $word,
                    'products' => ProductResource::collection($products),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'category' => 'Colchonería',
            'data' => $result,
        ]);
    }


    // -----------------------------
    // --- Crear producto ---
    // -----------------------------
    public function store(StoreProductRequest $request)
    {
        // Intentaremos crear el producto hasta que tengamos un slug válido
        $maxAttempts = 5; // Evita bucles infinitos
        $attempt = 0;
        do {
            $attempt++;

            // Generar slug único
            $slug = $this->generateUniqueSlug($request->name);

            try {
                // Crear producto
                $product = Product::create([
                    'id_product'    => $request->id_product,
                    'name'          => $request->name,
                    'slug'          => $slug,
                    'description'   => $request->description,
                    'price'         => $request->price,
                    'promo_price'   => $request->promo_price,
                    'is_promo'      => $request->boolean('is_promo'),
                    'promo_ends_at' => $request->promo_ends_at,
                    'quantity'      => $request->quantity,
                    'category_id'   => $request->category_id,
                ]);

                // Guardar imágenes si las hay
                if ($request->hasFile('images')) {
                    $position = 0;
                    foreach ($request->file('images') as $file) {
                        $path = $file->store('images/products', 'public');
                        $product->images()->create([
                            'image_path' => $path,
                            'position'   => $position++,
                        ]);
                    }
                }

                // Todo salió bien, rompemos el bucle
                break;

            } catch (\Illuminate\Database\QueryException $e) {
                // Si es error por slug duplicado, generamos otro y reintentamos
                if ($e->errorInfo[1] == 1062 && $attempt < $maxAttempts) {
                    // El bucle continúa
                } else {
                    // Otro error o intentos agotados
                    return response()->json([
                        'message' => 'No se pudo crear el producto.',
                        'error'   => $e->getMessage()
                    ], 500);
                }
            }

        } while ($attempt < $maxAttempts);

        return response()->json([
            'message' => 'Producto creado exitosamente',
            'product' => new ProductResource($product->load('images')),
        ], 201);
    }


    // -----------------------------
    // --- Mostrar producto ---
    // -----------------------------
    public function show($id)
    {
        $product = Product::with(['category', 'images'])->findOrFail($id);
        return new ProductResource($product);
    }

    // -----------------------------
// --- Paginación sin Colchonería ---
// -----------------------------
    public function getPaginatedWithoutColchoneria(Request $request)
    {
        $perPage = 60;
        $page = $request->input('page', 1);

        $query = Product::with(['category', 'images'])
            ->whereHas('category', function ($q) {
                $q->where('id', '!=', 76); // Excluir categoría Colchonería
            });

        // 🔎 Filtro opcional por búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // ↕️ Orden opcional
        if ($request->filled('sort')) {
            match ($request->sort) {
                'precio_asc'  => $query->orderBy('price', 'asc'),
                'precio_desc' => $query->orderBy('price', 'desc'),
                'nombre_asc'  => $query->orderBy('name', 'asc'),
                'nombre_desc' => $query->orderBy('name', 'desc'),
                default       => $query->latest(),
            };
        } else {
            $query->latest();
        }

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success'       => true,
            'section'       => 'general',
            'data'          => ProductResource::collection($products),
            'current_page'  => $products->currentPage(),
            'last_page'     => $products->lastPage(),
            'per_page'      => $products->perPage(),
            'total'         => $products->total(),
        ]);
    }



// -----------------------------
// --- Paginación de Colchonería (solo id = 76) ---
// -----------------------------
    public function getPaginatedColchoneria(Request $request)
    {
        $perPage = 60;
        $page = $request->input('page', 1);

        $query = Product::with(['category', 'images'])
            ->where('category_id', 76);

        // 🔎 Filtro opcional por marca o palabra clave
        if ($request->filled('brand')) {
            $brand = $request->brand;
            $query->where('name', 'LIKE', "%{$brand}%");
        }

        // ↕️ Orden opcional
        if ($request->filled('sort')) {
            match ($request->sort) {
                'precio_asc'  => $query->orderBy('price', 'asc'),
                'precio_desc' => $query->orderBy('price', 'desc'),
                'nombre_asc'  => $query->orderBy('name', 'asc'),
                'nombre_desc' => $query->orderBy('name', 'desc'),
                default       => $query->latest(),
            };
        } else {
            $query->latest();
        }

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success'       => true,
            'section'       => 'colchoneria',
            'data'          => ProductResource::collection($products),
            'current_page'  => $products->currentPage(),
            'last_page'     => $products->lastPage(),
            'per_page'      => $products->perPage(),
            'total'         => $products->total(),
        ]);
    }


    // -----------------------------
// --- Productos con nombre similar ---
// -----------------------------
    public function relatedByFirstWord($id)
    {
        // 1. Obtener producto actual
        $producto = Product::findOrFail($id);

        // 2. Obtener la primera palabra del nombre
        $firstWord = explode(' ', trim($producto->name))[0];

        // 3. Buscar productos que contengan esa palabra en el nombre (excepto el actual)
        $related = Product::with(['category', 'images'])
            ->where('id', '!=', $producto->id)
            ->where('name', 'LIKE', "%{$firstWord}%")
            ->take(6) // Limitar cantidad
            ->get();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($related),
        ]);
    }


    // -----------------------------
    // --- Actualizar producto ---
    // -----------------------------
    public function update(UpdateProductRequest $request, $id)
    {
        $product = Product::with('images')->findOrFail($id);

        $product->update([
            'id_product'    => $request->id_product,
            'name'          => $request->name,
            'slug'          => $this->generateUniqueSlug($request->name, $product->id),
            'description'   => $request->description,
            'price'         => $request->price,
            'promo_price'   => $request->promo_price,
            'is_promo'      => $request->boolean('is_promo'),
            'promo_ends_at' => $request->promo_ends_at,
            'quantity'      => $request->quantity,
            'category_id'   => $request->category_id,
        ]);

        // Eliminar imágenes seleccionadas
        if ($request->filled('delete_images')) {
            $idsToDelete = $request->input('delete_images');
            $imagesToDelete = $product->images()->whereIn('id', $idsToDelete)->get();

            foreach ($imagesToDelete as $img) {
                if (Storage::disk('public')->exists($img->image_path)) {
                    Storage::disk('public')->delete($img->image_path);
                }
                $img->delete();
            }
        }

        // Agregar nuevas imágenes
        if ($request->hasFile('images')) {
            $nextPosition = $product->images()->max('position') + 1 ?? 0;
            foreach ($request->file('images') as $file) {
                $path = $file->store('images/products', 'public');
                $product->images()->create([
                    'image_path' => $path,
                    'position'   => $nextPosition++,
                ]);
            }
        }

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'product' => new ProductResource($product->load('images')),
        ]);
    }

    // -----------------------------
    // --- Eliminar producto ---
    // -----------------------------
    public function destroy($id)
    {
        $product = Product::with('images')->findOrFail($id);

        foreach ($product->images as $img) {
            if (Storage::disk('public')->exists($img->image_path)) {
                Storage::disk('public')->delete($img->image_path);
            }
            $img->delete();
        }

        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    // -----------------------------
    // --- Limpiar promociones expiradas ---
    // -----------------------------
    public function cleanExpiredPromos()
    {
        $updated = Product::where('is_promo', true)
            ->whereNotNull('promo_ends_at')
            ->where('promo_ends_at', '<', Carbon::now())
            ->update([
                'is_promo'    => false,
                'promo_price' => null,
            ]);

        return response()->json([
            'message' => "Se limpiaron $updated productos con promos expiradas",
            'count'   => $updated,
        ]);
    }

    // -----------------------------
    // --- Búsqueda avanzada ---
    // -----------------------------
    public function search(Request $request)
    {
        // Construir la query principal con relaciones
        $query = Product::with(['category', 'images']);

        // -------------------------------
        // Filtro por búsqueda de texto
        // -------------------------------
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // -------------------------------
        // Filtro por categoría
        // -------------------------------
        if ($request->filled('category_id')) {
            $categoryId = (int) $request->category_id; // casteo a entero por seguridad
            $query->where('category_id', $categoryId);
        }

        // -------------------------------
        // Filtro por productos en promoción
        // -------------------------------
        if ($request->filled('is_promo')) {
            $isPromo = filter_var($request->is_promo, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_promo', $isPromo);
        }

        // -------------------------------
        // Ordenación
        // -------------------------------
        if ($request->filled('sort')) {
            match ($request->sort) {
                'precio_asc'  => $query->orderBy('price', 'asc'),
                'precio_desc' => $query->orderBy('price', 'desc'),
                'nombre_asc'  => $query->orderBy('name', 'asc'),
                'nombre_desc' => $query->orderBy('name', 'desc'),
                default       => $query->latest(),
            };
        } else {
            $query->latest();
        }

        // -------------------------------
        // Paginación
        // -------------------------------
        $perPage = 60; // productos por página
        $products = $query->paginate($perPage);

        // -------------------------------
        // Devolver colección con meta completo
        // -------------------------------
        return ProductResource::collection($products)->additional([
            'meta' => [
                'total'        => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
            ]
        ]);
    }


}
