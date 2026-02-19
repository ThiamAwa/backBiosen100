<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Models\TypeCategorie;
use Illuminate\Http\Request;

class CategorieController extends Controller
{
    public function index()
    {
        return response()->json(Categorie::with('typeCategorie')->paginate(10));
    }

    public function show($id)
    {
        return response()->json(Categorie::with(['typeCategorie', 'gammes', 'produits'])->findOrFail($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'               => 'required|string|max:255',
            'description'       => 'nullable|string|max:255',
            'type_categorie_id' => 'nullable|exists:type_categories,id',
        ]);
        $categorie = Categorie::create($validated);
        return response()->json($categorie->load('typeCategorie'), 201);
    }

    public function update(Request $request, $id)
    {
        $categorie = Categorie::findOrFail($id);
        $validated = $request->validate([
            'nom'               => 'required|string|max:255',
            'description'       => 'nullable|string|max:255',
            'type_categorie_id' => 'nullable|exists:type_categories,id',
        ]);
        $categorie->update($validated);
        return response()->json($categorie->load('typeCategorie'));
    }

    public function destroy($id)
    {
        $categorie = Categorie::findOrFail($id);
        $categorie->delete();
        return response()->json(['message' => 'Catégorie supprimée.']);
    }
}
