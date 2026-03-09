<?php

namespace App\Http\Controllers;

use App\Models\Gamme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GammeController extends Controller
{
    public function index()
    {
        $gammes = Gamme::with('typeCategorie')->withCount('produits')->paginate(10);
        return response()->json($gammes);
    }

    public function show($id)
    {
        return response()->json(Gamme::with(['typeCategorie', 'produits', 'avis.user'])->findOrFail($id));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nom'               => 'required|string|max:255',
                'description'       => 'nullable|string',
                'modeUtilisation'   => 'nullable|string|max:255',
                'prix'              => 'required|numeric|min:0',
                'prixPromo'         => 'nullable|numeric|min:0',
                'stock'             => 'required|integer|min:0',
                'type_categorie_id' => 'nullable|exists:type_categories,id',
                'image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('gammes', 'public');
            }
            $validated['enPromotion'] = $request->boolean('enPromotion');

            return response()->json(Gamme::create($validated), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Gamme store: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $gamme = Gamme::findOrFail($id);
            $validated = $request->validate([
                'nom'               => 'required|string|max:255',
                'description'       => 'nullable|string',
                'modeUtilisation'   => 'nullable|string|max:255',
                'prix'              => 'required|numeric|min:0',
                'prixPromo'         => 'nullable|numeric|min:0',
                'stock'             => 'required|integer|min:0',
                'type_categorie_id' => 'nullable|exists:type_categories,id',
                'image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'remove_image'      => 'nullable|boolean',
            ]);

            if ($request->boolean('remove_image')) {
                if ($gamme->image) Storage::disk('public')->delete($gamme->image);
                $validated['image'] = null;
            } elseif ($request->hasFile('image')) {
                if ($gamme->image) Storage::disk('public')->delete($gamme->image);
                $validated['image'] = $request->file('image')->store('gammes', 'public');
            } else {
                unset($validated['image']);
            }

            $validated['enPromotion'] = $request->boolean('enPromotion');
            $gamme->update($validated);
            return response()->json($gamme->load('typeCategorie'));

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $gamme = Gamme::findOrFail($id);
        if ($gamme->image) Storage::disk('public')->delete($gamme->image);
        $gamme->delete();
        return response()->json(['message' => 'Gamme supprimée.']);
    }
}
