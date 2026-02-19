<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Livreur;
use Illuminate\Http\Request;

class LivreurController extends Controller
{
    public function index()
    {
        return response()->json(Livreur::orderBy('created_at', 'desc')->paginate(10));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'      => 'required|string|max:255',
            'prenom'   => 'required|string|max:255',
            'telephone'=> 'required|string|max:20',
            'adresse'  => 'required|string|max:255',
        ]);
        return response()->json(Livreur::create($validated), 201);
    }

    public function update(Request $request, $id)
    {
        $livreur   = Livreur::findOrFail($id);
        $validated = $request->validate([
            'nom'      => 'required|string|max:255',
            'prenom'   => 'required|string|max:255',
            'telephone'=> 'required|string|max:20',
            'adresse'  => 'required|string|max:255',
        ]);
        $livreur->update($validated);
        return response()->json($livreur);
    }

    public function destroy($id)
    {
        Livreur::findOrFail($id)->delete();
        return response()->json(['message' => 'Livreur supprimé.']);
    }
}
