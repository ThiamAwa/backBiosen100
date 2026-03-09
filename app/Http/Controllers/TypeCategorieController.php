<?php

namespace App\Http\Controllers;

use App\Models\TypeCategorie;
use Illuminate\Http\Request;

class TypeCategorieController extends Controller
{
    public function index()
    {
        return response()->json(TypeCategorie::with('categories')->paginate(10));
    }

    public function show($id)
    {
        return response()->json(TypeCategorie::with('categories', 'gammes')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:type_categories,nom',
        ]);
        $tc = TypeCategorie::create($validated);
        return response()->json($tc, 201);
    }

    public function update(Request $request, $id)
    {
        $tc = TypeCategorie::findOrFail($id);
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:type_categories,nom,' . $id,
        ]);
        $tc->update($validated);
        return response()->json($tc);
    }

    public function destroy($id)
    {
        $tc = TypeCategorie::findOrFail($id);
        if ($tc->categories()->count() > 0) {
            return response()->json(['message' => 'Impossible : des catégories sont liées.'], 422);
        }
        $tc->delete();
        return response()->json(['message' => 'Type de catégorie supprimé.']);
    }
}
