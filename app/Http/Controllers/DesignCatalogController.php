<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DesignCatalog;

class DesignCatalogController extends Controller
{
    public function index()
    {
        return DesignCatalog::with('product')->orderBy('priority')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'style' => 'nullable|in:nordic,modern,minimalist,industrial,classic,boho',
            'room' => 'nullable|in:living_room,bedroom,dining_room,office',
            'color' => 'nullable|string|max:50',
            'width' => 'nullable|integer',
            'depth' => 'nullable|integer',
            'height' => 'nullable|integer',
            'priority' => 'nullable|integer',
        ]);

        $design = DesignCatalog::create($request->all());
        return response()->json($design->load('product'));
    }

    public function show($id)
    {
        return DesignCatalog::with('product')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $design = DesignCatalog::findOrFail($id);

        $request->validate([
            'style' => 'nullable|in:nordic,modern,minimalist,industrial,classic,boho',
            'room' => 'nullable|in:living_room,bedroom,dining_room,office',
            'color' => 'nullable|string|max:50',
            'width' => 'nullable|integer',
            'depth' => 'nullable|integer',
            'height' => 'nullable|integer',
            'priority' => 'nullable|integer',
        ]);

        $design->update($request->all());
        return response()->json($design->load('product'));
    }

    public function destroy($id)
    {
        $design = DesignCatalog::findOrFail($id);
        $design->delete();
        return response()->json(['message' => 'Eliminado correctamente']);
    }
}
