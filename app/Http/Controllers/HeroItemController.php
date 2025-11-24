<?php

namespace App\Http\Controllers;

use App\Models\HeroItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

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
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'link' => 'nullable|string|max:255',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4,webm,ogg|max:102400', // 100MB max
            'order' => 'nullable|integer',
            'status' => 'nullable|in:draft,published',
        ]);

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $file->storeAs('hero', $filename, 'public');

            $data['media_filename'] = $filename;
            $data['media_type'] = $file->getClientMimeType() ?? 'image';
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
            if ($item->media_filename) {
                Storage::disk('public')->delete('hero/' . $item->media_filename);
            }

            $file = $request->file('media');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $file->storeAs('hero', $filename, 'public');

            $data['media_filename'] = $filename;
            $data['media_type'] = $file->getClientMimeType() ?? 'image';
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
