<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\ProduitMedia;
use App\Models\Categorie;
use App\Models\TypeCategorie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProduitSportController extends Controller
{
    public function index()
    {
        try {
            $produits = Produit::with([
                'avis',
                'medias.typeCategorie'
            ])->paginate(10);

            $typeCategories = TypeCategorie::all();
            return response()->json(compact('produits', 'typeCategories'));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nom'               => 'required|string|max:255',
                'description'       => 'nullable|string',
                'prix'              => 'required|numeric|min:0',
                'prixPromo'         => 'nullable|numeric|min:0',
                'stock'             => 'required|integer|min:0',
                'type_categorie_id' => 'nullable|exists:type_categories,id', // ← WAS: categorie_id
                'images'            => 'nullable|array|max:10',
                'images.*'          => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'videos_urls'       => 'nullable|array|max:5',
                'videos_urls.*'     => 'nullable|url|max:500',
                'videos_titres'     => 'nullable|array',
                'videos_titres.*'   => 'nullable|string|max:255',
            ]);

            // Extraire type_categorie_id AVANT de créer le produit
            $typeCategorieId = $validated['type_categorie_id'] ?? null;
            unset($validated['type_categorie_id']); // ← ne pas mettre sur la table produits

            $validated['enPromotion'] = $request->boolean('enPromotion');

            DB::beginTransaction();

            $produit = Produit::create($validated);
            $ordre = 0;

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    if (!$imageFile->isValid()) continue;
                    $chemin = $imageFile->store('produits_sport', 'public');
                    $estPrincipal = ($ordre === 0);
                    ProduitMedia::create([
                        'produit_id'        => $produit->id,
                        'type'              => 'image',
                        'chemin'            => $chemin,
                        'ordre'             => $ordre,
                        'est_principal'     => $estPrincipal,
                        'type_categorie_id' => $typeCategorieId, // ← injecté ici
                    ]);
                    if ($estPrincipal) $produit->update(['image' => 'storage/' . $chemin]);
                    $ordre++;
                }
            }

            if ($request->filled('videos_urls')) {
                foreach ($request->input('videos_urls') as $i => $url) {
                    if (empty(trim($url))) continue;
                    ProduitMedia::create([
                        'produit_id'        => $produit->id,
                        'type'              => 'video_url',
                        'url_externe'       => trim($url),
                        'titre'             => $request->input("videos_titres.$i"),
                        'ordre'             => $ordre,
                        'est_principal'     => false,
                        'type_categorie_id' => $typeCategorieId, // ← injecté ici aussi
                    ]);
                    $ordre++;
                }
            }

            DB::commit();
            return response()->json($produit->load('medias.typeCategorie'), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $produit = Produit::with('medias')->findOrFail($id);
            $validated = $request->validate([
                'nom'                  => 'required|string|max:255',
                'description'          => 'nullable|string',
                'prix'                 => 'required|numeric|min:0',
                'prixPromo'            => 'nullable|numeric|min:0',
                'stock'                => 'required|integer|min:0',
                'type_categorie_id'    => 'nullable|exists:type_categories,id', // ← corrigé
                'medias_a_supprimer'   => 'nullable|array',
                'medias_a_supprimer.*' => 'integer|exists:produit_medias,id',
                'media_principal_id'   => 'nullable|integer|exists:produit_medias,id',
                'images'               => 'nullable|array|max:10',
                'images.*'             => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'videos_urls'          => 'nullable|array|max:5',
                'videos_urls.*'        => 'nullable|url|max:500',
                'videos_titres'        => 'nullable|array',
                'videos_titres.*'      => 'nullable|string|max:255',
            ]);

            $typeCategorieId = $request->input('type_categorie_id'); // ← récupérer
            $validated['enPromotion'] = $request->boolean('enPromotion');
            unset($validated['type_categorie_id']); // ← ne pas sauvegarder sur produits

            DB::beginTransaction();

            // Mettre à jour type_categorie_id sur tous les médias existants
            if ($typeCategorieId !== null) {
                ProduitMedia::where('produit_id', $produit->id)
                    ->update(['type_categorie_id' => $typeCategorieId]); // ← mettre à jour
            }

            // Supprimer médias cochés
            if (!empty($validated['medias_a_supprimer'])) {
                foreach ($validated['medias_a_supprimer'] as $mediaId) {
                    $media = ProduitMedia::where('id', $mediaId)->where('produit_id', $produit->id)->first();
                    if ($media) {
                        if ($media->chemin) Storage::disk('public')->delete($media->chemin);
                        $media->delete();
                    }
                }
            }

            $ordre = (ProduitMedia::where('produit_id', $produit->id)->max('ordre') ?? -1) + 1;

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    if (!$imageFile->isValid()) continue;
                    ProduitMedia::create([
                        'produit_id'        => $produit->id,
                        'type'              => 'image',
                        'chemin'            => $imageFile->store('produits_sport', 'public'),
                        'ordre'             => $ordre++,
                        'est_principal'     => false,
                        'type_categorie_id' => $typeCategorieId, // ← sauvegarder
                    ]);
                }
            }

            if ($request->filled('videos_urls')) {
                foreach ($request->input('videos_urls') as $i => $url) {
                    if (empty(trim($url))) continue;
                    ProduitMedia::create([
                        'produit_id'        => $produit->id,
                        'type'              => 'video_url',
                        'url_externe'       => trim($url),
                        'titre'             => $request->input("videos_titres.$i"),
                        'ordre'             => $ordre++,
                        'est_principal'     => false,
                        'type_categorie_id' => $typeCategorieId, // ← sauvegarder
                    ]);
                }
            }

            if (!empty($validated['media_principal_id'])) {
                ProduitMedia::where('produit_id', $produit->id)->update(['est_principal' => false]);
                ProduitMedia::where('id', $validated['media_principal_id'])->update(['est_principal' => true]);
            }

            if (!ProduitMedia::where('produit_id', $produit->id)->where('est_principal', true)->exists()) {
                optional(ProduitMedia::where('produit_id', $produit->id)->where('type', 'image')->orderBy('ordre')->first())->update(['est_principal' => true]);
            }

            $principal = ProduitMedia::where('produit_id', $produit->id)->where('est_principal', true)->first();
            if ($principal?->chemin) $validated['image'] = 'storage/' . $principal->chemin;

            $produit->update($validated);
            DB::commit();

            return response()->json($produit->load('medias.typeCategorie'));

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    private function getTypeSport(): TypeCategorie
    {
        return TypeCategorie::where('nom', 'Sport')->firstOrFail();
    }
    public function show($id)
    {
        $produit = Produit::with(['categorie', 'medias', 'avis.user'])->findOrFail($id);
        return response()->json($produit);
    }

    public function destroy($id)
    {
        $produit = Produit::with('medias')->findOrFail($id);
        foreach ($produit->medias as $media) {
            if ($media->chemin) Storage::disk('public')->delete($media->chemin);
        }
        $produit->delete();
        return response()->json(['message' => 'Produit sport supprimé.']);
    }

    public function getMedias($id)
    {
        $produit = Produit::with('medias')->findOrFail($id);
        $medias = $produit->medias->map(fn($m) => [
            'id'           => $m->id,
            'type'         => $m->type,
            'url'          => $m->url,
            'embed_url'    => $m->embed_url,
            'thumbnail'    => $m->youtube_thumbnail,
            'titre'        => $m->titre,
            'est_principal'=> $m->est_principal,
        ]);
        return response()->json(['produit' => ['id' => $produit->id, 'nom' => $produit->nom], 'medias' => $medias]);
    }
}
