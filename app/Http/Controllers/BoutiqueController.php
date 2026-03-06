<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Boutique;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BoutiqueController extends Controller
{
    public function index()
    {
        return response()->json(
            Boutique::withCount('users')->with(['users.role'])->orderBy('created_at', 'desc')->paginate(10)
        );
    }

    public function show($id)
    {
        return response()->json(Boutique::with(['users.role'])->findOrFail($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'         => 'required|string|max:255',
            'adresse'     => 'required|string|max:255',
            
        ]);
        return response()->json(Boutique::create($validated), 201);
    }

    public function update(Request $request, $id)
    {
        $boutique  = Boutique::findOrFail($id);
        $validated = $request->validate([
            'nom'         => 'required|string|max:255',
            'adresse'     => 'required|string|max:255',
            
        ]);
        $boutique->update($validated);
        return response()->json($boutique);
    }

    public function destroy($id)
    {
        $boutique = Boutique::findOrFail($id);
        if ($boutique->users()->count() > 0) {
            return response()->json(['message' => 'Impossible : cette boutique a du personnel.'], 422);
        }
        $boutique->delete();
        return response()->json(['message' => 'Boutique supprimée.']);
    }
}
