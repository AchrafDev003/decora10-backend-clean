<?php

namespace App\Http\Controllers;

use App\Http\Resources\PackResource;
use App\Models\Pack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Cloudinary\Cloudinary;

class PackController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', \App\Http\Middleware\CheckUserRole::class . ':admin,dueno'])
            ->only(['store', 'update', 'destroy', 'indexAdmin']);
    }

    /* =====================================
       Slug único
    ===================================== */
    private function generateUniqueSlug(string $title, ?int $packId = null): string
    {
        $base = Str::slug($title);
        $slug = $base . '-' . substr(md5(uniqid()), 0, 6);

        return Pack::where('slug', $slug)
            ->when($packId, fn ($q) => $q->where('id', '!=', $packId))
            ->exists()
            ? $slug . '-' . uniqid()
            : $slug;
    }


    private function deleteCloudinaryImage(?string $url): void
    {
        if (!$url) return;

        try {
            $cloudinary = new Cloudinary(config('services.cloudinary.url'));

            // Extraer public_id desde la URL
            $path = parse_url($url, PHP_URL_PATH);
            $path = ltrim($path, '/');
            $path = preg_replace('#^image/upload/v\d+/#', '', $path);
            $publicId = preg_replace('/\.[^.]+$/', '', $path);

            $cloudinary->uploadApi()->destroy($publicId);
        } catch (\Throwable $e) {
            logger()->warning('Cloudinary delete failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /* =====================================
       Packs activos (público)
    ===================================== */
    public function index()
    {
        return PackResource::collection(
            Pack::with('items')
                ->active()
                ->orderBy('ends_at')
                ->get()
        );
    }

    // Endpoint para packs limitados
    public function limited()
    {
        // Traemos solo packs activos, ordenados por fecha de fin
        $packs = Pack::with('items')
            ->active()            // scopeActive() en el modelo
            ->orderBy('ends_at', 'asc')
            ->get();

        return PackResource::collection($packs);
    }

    /* =====================================
       Mostrar pack por slug
    ===================================== */
    public function show(string $slug)
    {
        $pack = Pack::with('items')
            ->where('slug', $slug)
            ->firstOrFail();

        return new PackResource($pack);
    }

    /* =====================================
       Admin index
    ===================================== */
    public function indexAdmin(Request $request)
    {
        $query = Pack::with('items');

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->active(),
                'expired' => $query->where('ends_at', '<', now()),
                'scheduled' => $query->where('starts_at', '>', now()),
                default => null,
            };
        }

        return PackResource::collection(
            $query->orderByDesc('created_at')->get()
        );
    }

    /* =====================================
       Crear pack
    ===================================== */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'original_price' => 'required|numeric|min:0',
            'promo_price'    => 'required|numeric|min:0|lte:original_price',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after:starts_at',
            'is_active'      => 'boolean',
            'requires_measure' => 'boolean', // ✅ NUEVO

            'image' => 'required|image|max:4096',

            'items'                 => 'required|array|min:1',
            'items.*.name'          => 'required|string|max:255',
            'items.*.description'      => 'nullable|string',          // ✅ NUEVO
            'items.*.type'          => 'nullable|string|max:50',
            'items.*.price'         => 'required|numeric|min:0',
            'items.*.quantity'      => 'integer|min:1',
            'items.*.image'         => 'required|image|max:4096',
        ]);

        DB::beginTransaction();

        try {
            $cloudinary = new Cloudinary(config('services.cloudinary.url'));

            // Imagen del pack
            $packImage = $cloudinary->uploadApi()->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'packs']
            );

            $pack = Pack::create([
                'title' => $validated['title'],
                'slug' => $this->generateUniqueSlug($validated['title']),
                'description' => $validated['description'] ?? null,
                'original_price' => $validated['original_price'],
                'promo_price' => $validated['promo_price'],
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'is_active' => $request->boolean('is_active', true),
                'requires_measure' => $request->boolean('requires_measure', false), // ✅
                'image_url' => $packImage['secure_url'],
            ]);


            // Items
            foreach ($validated['items'] as $index => $item) {
                $itemImage = $cloudinary->uploadApi()->upload(
                    $item['image']->getRealPath(),
                    ['folder' => 'pack-items']
                );

                $pack->items()->create([
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null, // ✅
                    'type' => $item['type'] ?? null,
                    'price' => $item['price'],
                    'quantity' => $item['quantity'] ?? 1,
                    'image_url' => $itemImage['secure_url'],
                    'sort_order' => $index,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new PackResource($pack->load('items')),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el pack',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /* =====================================
       Actualizar pack
    ===================================== */
    public function update(Request $request, int $id)
    {
        $pack = Pack::with('items')->findOrFail($id);

        $validated = $request->validate([
            'title'             => 'sometimes|required|string|max:255',
            'description'       => 'nullable|string',
            'original_price'    => 'sometimes|required|numeric|min:0',
            'promo_price'       => 'sometimes|required|numeric|min:0|lte:original_price',
            'starts_at'         => 'sometimes|required|date',
            'ends_at'           => 'sometimes|required|date|after:starts_at',
            'is_active'         => 'boolean',
            'requires_measure'  => 'boolean',

            'image' => 'nullable|image|max:4096',

            'items'                    => 'nullable|array|min:1',
            'items.*.id'               => 'nullable|exists:pack_items,id',
            'items.*.name'             => 'required|string|max:255',
            'items.*.description'      => 'nullable|string',
            'items.*.type'             => 'nullable|string|max:50',
            'items.*.price'            => 'required|numeric|min:0',
            'items.*.quantity'         => 'nullable|integer|min:1',
            'items.*.image'            => 'nullable|image|max:4096',
        ]);

        DB::beginTransaction();

        try {
            $cloudinary = new Cloudinary(config('services.cloudinary.url'));

            /* =====================
               Imagen del pack
            ===================== */
            if ($request->hasFile('image')) {
                // (opcional) borrar imagen antigua si guardas public_id
                $packImage = $cloudinary->uploadApi()->upload(
                    $request->file('image')->getRealPath(),
                    ['folder' => 'packs']
                );

                $pack->image_url = $packImage['secure_url'];
            }

            /* =====================
               Slug
            ===================== */
            if ($request->filled('title')) {
                $pack->slug = $this->generateUniqueSlug($request->title, $pack->id);
            }

            /* =====================
               Update pack
            ===================== */
            $pack->update($request->only([
                'title',
                'description',
                'original_price',
                'promo_price',
                'starts_at',
                'ends_at',
                'is_active',
                'requires_measure',
            ]));

            /* =====================
               Sync items
            ===================== */
            if (!empty($validated['items'])) {

                $incomingIds = collect($validated['items'])
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                // Eliminar items que ya no existen
                $pack->items()
                    ->whereNotIn('id', $incomingIds)
                    ->delete();

                foreach ($validated['items'] as $index => $item) {

                    $data = [
                        'name'        => $item['name'],
                        'description' => $item['description'] ?? null,
                        'type'        => $item['type'] ?? null,
                        'price'       => $item['price'],
                        'quantity'    => $item['quantity'] ?? 1,
                        'sort_order'  => $index,
                    ];

                    // Nueva imagen de item
                    if (!empty($item['image'])) {
                        $itemImage = $cloudinary->uploadApi()->upload(
                            $item['image']->getRealPath(),
                            ['folder' => 'pack-items']
                        );
                        $data['image_url'] = $itemImage['secure_url'];
                    }

                    if (!empty($item['id'])) {
                        // Update item existente
                        $pack->items()
                            ->where('id', $item['id'])
                            ->update($data);
                    } else {
                        // Crear nuevo item
                        $pack->items()->create($data);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => new PackResource($pack->load('items')),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el pack',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    /* =====================================
       Eliminar pack
    ===================================== */
    public function destroy(int $id)
    {
        $pack = Pack::with('items')->findOrFail($id);

        DB::beginTransaction();

        try {
            // 🧹 Borrar imagen principal del pack
            $this->deleteCloudinaryImage($pack->image_url);

            // 🧹 Borrar imágenes de los items
            foreach ($pack->items as $item) {
                $this->deleteCloudinaryImage($item->image_url);
            }

            // 🗑️ Borrar pack (cascade items si está configurado)
            $pack->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pack e imágenes eliminados correctamente',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el pack',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
