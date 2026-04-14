<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BoutiqueController extends Controller
{
    /**
     * Affiche la liste paginée des boutiques avec leur personnel.
     */
    public function index()
    {
        $boutiques = Boutique::withCount('users')
            ->with(['users.role'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($boutiques);
    }

    /**
     * Affiche une boutique spécifique avec son personnel.
     */
    public function show($id)
    {
        $boutique = Boutique::with(['users.role'])->findOrFail($id);
        return response()->json($boutique);
    }

    /**
     * Crée une nouvelle boutique avec upload d'image optionnel.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'     => 'required|string|max:255',
            'adresse' => 'required|string|max:255',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg,gif',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('boutiques', 'public');
            $validated['image'] = $path;
        }

        $boutique = Boutique::create($validated);
        return response()->json($boutique, 201);
    }

    /**
     * Met à jour une boutique existante.
     * Si une nouvelle image est fournie, l'ancienne est supprimée.
     */
    public function update(Request $request, $id)
    {
        $boutique = Boutique::findOrFail($id);

        $validated = $request->validate([
            'nom'     => 'required|string|max:255',
            'adresse' => 'required|string|max:255',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg,gif',
        ]);

        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image
            if ($boutique->image) {
                Storage::disk('public')->delete($boutique->image);
            }
            $path = $request->file('image')->store('boutiques', 'public');
            $validated['image'] = $path;
        } else {
            // Ne pas toucher à l'image existante
            unset($validated['image']);
        }

        $boutique->update($validated);
        return response()->json($boutique);
    }

    /**
     * Supprime une boutique à condition qu'elle n'ait pas de personnel.
     * L'image associée est également supprimée du stockage.
     */
    public function destroy($id)
    {
        $boutique = Boutique::findOrFail($id);

        if ($boutique->users()->count() > 0) {
            return response()->json([
                'message' => 'Impossible : cette boutique a du personnel.'
            ], 422);
        }

        if ($boutique->image) {
            Storage::disk('public')->delete($boutique->image);
        }

        $boutique->delete();
        return response()->json(['message' => 'Boutique supprimée.']);
    }
}