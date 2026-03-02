<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\Categorie;
use App\Models\TypeCategorie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProduitController extends Controller
{
    private function getTypeBio()
{
    $typeBio = TypeCategorie::where('nom', 'Bio')->first();
    if (!$typeBio) {
        
        $typeBio = TypeCategorie::create(['nom' => 'Bio']);
       
    }
    return $typeBio;
}

    public function index()
    {
        try {
            $typeBio = $this->getTypeBio();
            $categorieIds = Categorie::where('type_categorie_id', $typeBio->id)->pluck('id');
            $produits = Produit::with(['categorie', 'gammes', 'avis'])
                ->whereIn('categorie_id', $categorieIds)
                ->paginate(10);
            $categories = Categorie::where('type_categorie_id', $typeBio->id)->get();
            return response()->json(compact('produits', 'categories'));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        return response()->json(Produit::with(['categorie', 'gammes', 'avis.user'])->findOrFail($id));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nom'          => 'required|string|max:255',
                'description'  => 'nullable|string',
                'modeUtilisation' => 'nullable|string|max:255',
                'prix'         => 'required|numeric|min:0',
                'prixPromo'    => 'nullable|numeric|min:0',
                'stock'        => 'required|integer|min:0',
                'categorie_id' => 'nullable|exists:categories,id',
                'gammes'       => 'nullable|array',
                'gammes.*'     => 'exists:gammes,id',
                'image'        => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('produits', 'public');
            }
            $validated['enPromotion'] = $request->boolean('enPromotion');

            $produit = Produit::create($validated);
            if ($request->has('gammes')) {
                $produit->gammes()->sync($request->gammes);
            }
            return response()->json($produit->load(['categorie', 'gammes']), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $produit = Produit::findOrFail($id);
            $validated = $request->validate([
                'nom'          => 'required|string|max:255',
                'description'  => 'nullable|string',
                'modeUtilisation' => 'nullable|string|max:255',
                'prix'         => 'required|numeric|min:0',
                'prixPromo'    => 'nullable|numeric|min:0',
                'stock'        => 'required|integer|min:0',
                'categorie_id' => 'nullable|exists:categories,id',
                'gammes'       => 'nullable|array',
                'gammes.*'     => 'exists:gammes,id',
                'image'        => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'remove_image' => 'nullable|boolean',
            ]);

            if ($request->boolean('remove_image')) {
                if ($produit->image) Storage::disk('public')->delete($produit->image);
                $validated['image'] = null;
            } elseif ($request->hasFile('image')) {
                if ($produit->image) Storage::disk('public')->delete($produit->image);
                $validated['image'] = $request->file('image')->store('produits', 'public');
            } else {
                unset($validated['image']);
            }

            $validated['enPromotion'] = $request->boolean('enPromotion');
            $produit->update($validated);
            $produit->gammes()->sync($request->gammes ?? []);

            return response()->json($produit->load(['categorie', 'gammes']));

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $produit = Produit::findOrFail($id);
        if ($produit->image) Storage::disk('public')->delete($produit->image);
        $produit->gammes()->detach();
        $produit->delete();
        return response()->json(['message' => 'Produit supprimé.']);
    }
}
