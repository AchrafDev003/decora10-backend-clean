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

    // ----------------------------------
    // Slug único
    // ----------------------------------
    private function generateUniqueSlug(string $title, ?int $packId = null): string
    {
        $baseSlug = Str::slug($title);

        // Añadimos un sufijo único basado en timestamp + microsegundos
        $uniqueSuffix = substr(md5(now()->timestamp . rand()), 0, 6);

        $slug = "{$baseSlug}-{$uniqueSuffix}";

        // Opcional: aseguramos que no exista un slug igual solo una vez
        $exists = Pack::where('slug', $slug)
            ->when($packId !== null, fn($q) => $q->where('id', '!=', $packId))
            ->exists();

        if ($exists) {
            // Si chocara (muy raro), usamos uniqid
            $slug = "{$baseSlug}-" . uniqid();
        }

        return $slug;
    }



    // ----------------------------------
    // Packs activos (público)
    // ----------------------------------
    public function index()
    {
        $packs = Pack::with(['items', 'images'])
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderBy('ends_at')
            ->get();

        return PackResource::collection($packs);
    }

    // ----------------------------------
    // Mostrar pack por slug
    // ----------------------------------
    public function show(string $slug)
    {
        $pack = Pack::with(['items', 'images'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new PackResource($pack);
    }

    // ----------------------------------
    // Admin index (activos / inactivos)
    // ----------------------------------
    public function indexAdmin(Request $request)
    {
        $query = Pack::with(['items', 'images']);

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query
                    ->where('is_active', true)
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now()),

                'expired' => $query->where('ends_at', '<', now()),
                'scheduled' => $query->where('starts_at', '>', now()),
                default => null,
            };
        }

        return PackResource::collection(
            $query->orderByDesc('created_at')->get()
        );
    }

    // ----------------------------------
    // Crear pack
    // ----------------------------------
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

            'items'                => 'array',
            'items.*.name'         => 'required|string|max:255',
            'items.*.type'         => 'nullable|string|max:50',
            'items.*.quantity'     => 'integer|min:1',

            'images.*'             => 'image|max:4096',
        ]);

        DB::beginTransaction();

        try {
            $pack = Pack::create([
                ...$validated,
                'slug' => $this->generateUniqueSlug($validated['title']),
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Items
            foreach ($request->input('items', []) as $index => $item) {
                $pack->items()->create([
                    'name' => $item['name'],
                    'type' => $item['type'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'sort_order' => $index,
                ]);
            }

            // Imágenes
            if ($request->hasFile('images')) {
                $cloudinary = new Cloudinary(config('services.cloudinary.url'));
                $position = 0;

                foreach ($request->file('images') as $file) {
                    $upload = $cloudinary->uploadApi()->upload(
                        $file->getRealPath(),
                        ['folder' => 'packs']
                    );

                    $pack->images()->create([
                        'image_path' => $upload['secure_url'],
                        'sort_order' => $position,
                        'is_main' => $position === 0,
                    ]);

                    $position++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new PackResource($pack->load(['items', 'images']))
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

    // ----------------------------------
    // Actualizar pack
    // ----------------------------------
    public function update(Request $request, int $id)
    {
        $pack = Pack::findOrFail($id);

        DB::beginTransaction();

        try {
            if ($request->filled('title')) {
                $pack->slug = $this->generateUniqueSlug($request->title, $pack->id);
            }

            $pack->update(
                $request->only([
                    'title',
                    'description',
                    'original_price',
                    'promo_price',
                    'starts_at',
                    'ends_at',
                    'is_active',
                ])
            );

            DB::commit();

            return new PackResource($pack->load(['items', 'images']));

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el pack',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ----------------------------------
    // Eliminar pack
    // ----------------------------------
    public function destroy(int $id)
    {
        $pack = Pack::with('images')->findOrFail($id);

        try {
            $cloudinary = new Cloudinary(config('services.cloudinary.url'));

            foreach ($pack->images as $img) {
                if ($img->image_path) {
                    $publicId = pathinfo(parse_url($img->image_path, PHP_URL_PATH), PATHINFO_FILENAME);
                    $cloudinary->uploadApi()->destroy("packs/{$publicId}");
                }
                $img->delete();
            }

            $pack->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pack eliminado correctamente'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el pack',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

