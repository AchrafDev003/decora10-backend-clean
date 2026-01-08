<?php

namespace App\Http\Controllers;

use App\Models\HeroItem;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Cloudinary\Cloudinary;

class HeroItemController extends Controller
{
    // 🔹 Solo admin y dueño pueden modificar
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store', 'update', 'destroy', 'toggle']);
        $this->middleware(\App\Http\Middleware\CheckUserRole::class . ':admin,dueno')
            ->only(['store', 'update', 'destroy', 'toggle']);
    }

    // ==========================
    // 🔹 LISTAR HERO ITEMS
    // ==========================
    public function index(Request $request)
    {
        $query = HeroItem::query();

        // Permite filtrar por estado (draft / published)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->orderBy('order')->get();
    }

    // ==========================
    // 🔹 MOSTRAR UNO
    // ==========================
    public function show($id)
    {
        return HeroItem::findOrFail($id);
    }

    // ==========================
    // 🔹 CREAR HERO ITEM
    // ==========================
    // ==========================
    // 🔹 CREAR HERO ITEM
    // ==========================
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'link' => 'nullable|string|max:255',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4,webm,ogg|max:102400', // 100MB
            'order' => 'nullable|integer',
            'status' => 'nullable|in:draft,published',
        ]);

        if ($request->hasFile('media')) {
            $cloudinary = new Cloudinary('cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io');

            $file = $request->file('media');
            $ext = $file->getClientOriginalExtension();
            $type = Str::startsWith($file->getMimeType(), 'video') ? 'video' : 'image';
            $publicId = "hero/" . Str::random(20);

            $result = $cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                [
                    'folder' => 'hero',
                    'public_id' => $publicId,
                    'resource_type' => $type,
                    'overwrite' => true,
                ]
            );

            $data['media_filename'] = $result['secure_url'] ?? null;
            $data['media_type'] = $type;
        }

        $item = HeroItem::create($data);

        return response()->json($item, 201);
    }

    // ==========================
    // 🔹 ACTUALIZAR HERO ITEM
    // ==========================
    public function update(Request $request, $id)
    {
        $item = HeroItem::findOrFail($id);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'sometimes|nullable|string|max:255',
            'descripcion' => 'sometimes|nullable|string',
            'link' => 'nullable|string|max:255',
            'media' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,gif,mp4,webm,ogg|max:102400',
            'order' => 'sometimes|integer',
            'status' => 'sometimes|in:draft,published',
        ]);

        if ($request->hasFile('media')) {
            $cloudinary = new Cloudinary('cloudinary://671366917242686:im5sL8H4zDJr9TrfcM70hOLSOUI@dvo9uq7io');

            // Eliminar archivo anterior de Cloudinary si existe
            if ($item->media_filename) {
                $path = parse_url($item->media_filename, PHP_URL_PATH);
                $filename = pathinfo($path, PATHINFO_FILENAME);
                (new UploadApi())->destroy("hero/{$filename}", ['resource_type' => $item->media_type]);
            }

            $file = $request->file('media');
            $type = Str::startsWith($file->getMimeType(), 'video') ? 'video' : 'image';
            $publicId = "hero/" . Str::random(20);

            $result = $cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                [
                    'folder' => 'hero',
                    'public_id' => $publicId,
                    'resource_type' => $type,
                    'overwrite' => true,
                ]
            );

            $data['media_filename'] = $result['secure_url'] ?? null;
            $data['media_type'] = $type;
        }

        $item->update($data);

        return response()->json($item);
    }


    // ==========================
    // 🔹 ELIMINAR HERO ITEM
    // ==========================
    public function destroy($id)
    {
        $item = HeroItem::findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Item eliminado correctamente']);
    }

    // ==========================
    // 🔹 CAMBIAR ESTADO (draft/published)
    // ==========================
    public function toggle($id)
    {
        $item = HeroItem::findOrFail($id);

        $item->status = $item->status === 'published' ? 'draft' : 'published';
        $item->save();

        return response()->json([
            'message' => "Estado cambiado a {$item->status}.",
            'hero_item' => $item
        ]);
    }
}
