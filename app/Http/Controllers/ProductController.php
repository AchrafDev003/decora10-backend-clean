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
use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\DB; // ✅ IMPORTANTE

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

        $query = Product::with(['category', 'images'])
            ->where('quantity', '>', 1); // <- SOLO productos con stock mayor a 1;

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

        return ProductResource::collection($products);


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
        $cloudinary = new Cloudinary(
            'cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io'
        );

        DB::beginTransaction();

        try {
            $slug = $this->generateUniqueSlug($request->name);

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

                // 🔥 LOGÍSTICA
                'logistic_type' => $request->logistic_type,

                'category_id'   => $request->category_id,
            ]);

            if ($request->hasFile('images')) {
                $position = 0;

                foreach ($request->file('images') as $file) {
                    $publicId = 'products/' . Str::slug($product->name) . '-' . uniqid();

                    $result = $cloudinary->uploadApi()->upload(
                        $file->getRealPath(),
                        [
                            'public_id'     => $publicId,
                            'resource_type' => 'image',
                            'overwrite'     => true,
                        ]
                    );

                    $product->images()->create([
                        'image_path' => $result['secure_url'],
                        'position'   => $position++,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Producto creado exitosamente',
                'product' => new ProductResource($product->load('images')),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear el producto',
                'error'   => $e->getMessage(),
            ], 500);
        }
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
            ->where('quantity', '>', 0) // <- SOLO productos con stock mayor a 1
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

        return ProductResource::collection($products);

    }



// -----------------------------
// --- Paginación de Colchonería (solo id = 76) ---
// -----------------------------
    public function getPaginatedColchoneria(Request $request)
    {
        $perPage = 60;
        $page = $request->input('page', 1);

        $query = Product::with(['category', 'images'])
            ->where('quantity', '>', 0) // <- SOLO productos con stock mayor a 1
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

        return ProductResource::collection($products);

    }


    // -----------------------------
// --- Productos con nombre similar ---
// -----------------------------
    public function relatedByFirstWord($id)
    {
        // 1. Obtener producto actual
        $producto = Product::where('id', $id)
            ->where('quantity', '>', 0)
            ->firstOrFail();  // devuelve el modelo directamente

        $firstWord = explode(' ', trim($producto->name))[0];


        // 3. Buscar productos que contengan esa palabra en el nombre (excepto el actual)
        $related = Product::with(['category', 'images'])
            ->where('id', '!=', $producto->id)
            ->where('name', 'LIKE', "%{$firstWord}%")
            ->take(8) // Limitar cantidad
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

        DB::beginTransaction();

        try {
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
                'logistic_type' => $request->logistic_type, // 👈 AÑADIDO
            ]);

            $cloudinary = new Cloudinary(
                'cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io'
            );

            /* 🗑️ ELIMINAR IMÁGENES */
            if ($request->filled('delete_images')) {
                $idsToDelete = $request->input('delete_images');

                $imagesToDelete = $product->images()
                    ->whereIn('id', $idsToDelete)
                    ->get();

                foreach ($imagesToDelete as $img) {
                    if ($img->image_path && str_contains($img->image_path, 'res.cloudinary.com')) {
                        $path = parse_url($img->image_path, PHP_URL_PATH);
                        $filename = pathinfo($path, PATHINFO_FILENAME);

                        if ($filename) {
                            $cloudinary->uploadApi()->destroy("products/{$filename}");
                        }
                    }

                    $img->delete();
                }
            }

            /* ➕ AGREGAR NUEVAS IMÁGENES */
            if ($request->hasFile('images')) {
                $currentMax   = $product->images()->max('position');
                $nextPosition = is_null($currentMax) ? 0 : $currentMax + 1;

                foreach ($request->file('images') as $file) {
                    $publicId = "products/" . Str::slug($product->name) . "-" . uniqid();

                    $result = $cloudinary->uploadApi()->upload(
                        $file->getRealPath(),
                        [
                            'public_id'     => $publicId,
                            'resource_type' => 'image',
                            'overwrite'     => true,
                        ]
                    );

                    $product->images()->create([
                        'image_path' => $result['secure_url'],
                        'position'   => $nextPosition++,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Producto actualizado correctamente',
                'product' => new ProductResource($product->load('images', 'category')),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar el producto',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }




    // -----------------------------
    // --- Eliminar producto ---
    // -----------------------------


    public function destroy($id)
    {
        $product = Product::with('images')->findOrFail($id);

        try {
            // 🔧 Cloudinary igual que en los otros métodos
            $cloudinary = new Cloudinary(
                'cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io'
            );

            foreach ($product->images as $img) {

                // 🔥 Eliminar imagen en Cloudinary si existe
                if ($img->image_path && str_contains($img->image_path, 'res.cloudinary.com')) {

                    $path = parse_url($img->image_path, PHP_URL_PATH);
                    $filename = pathinfo($path, PATHINFO_FILENAME);

                    if ($filename) {
                        $cloudinary->uploadApi()->destroy("products/{$filename}");
                    }
                }

                // 🧹 Eliminar registro de la imagen en BD
                $img->delete();
            }

            // 🗑️ Eliminar producto
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado correctamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el producto',
                'error'   => $e->getMessage(),
            ], 500);
        }
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
        $query = Product::with(['category', 'images'])
            ->where('quantity', '>', 1);

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
        return ProductResource::collection($products);

    }
    public function searchAdmin(Request $request)
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
        return ProductResource::collection($products);

    }

    public function quickSearch(Request $request)
    {
        $query = $request->query('query');

        if (!$query || strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $products = Product::query()
            ->select('id', 'name', 'price', 'quantity')
            ->with(['images' => function ($q) {
                $q->select('id', 'product_id', 'image_path');
            }])
            ->where('quantity', '>', 1) // ✅ FILTRO CORRECTO
            ->where('name', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get()
            ->map(function ($p) {
                return [
                    'id'       => $p->id,
                    'name'     => $p->name,
                    'price'    => $p->price,
                    'quantity' => $p->quantity,
                    'image'    => $p->images->first()->image_path ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }



}
