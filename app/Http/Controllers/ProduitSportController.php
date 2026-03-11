<?php

namespace App\Http\Controllers;

use App\Models\ProduitMedia;
use App\Models\TypeCategorie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProduitSportController extends Controller
{
    // ══════════════════════════════════════════════════════
    // HELPER PRIVÉ — Convertit image en array quoi qu'il arrive
    // ══════════════════════════════════════════════════════

    /**
     * Récupère les images d'un produit sous forme de tableau PHP propre.
     * Gère : null | string simple | string JSON | array
     */
    private function getImagesArray(ProduitMedia $produit): array
    {
        // Lire la valeur brute en base (contourne le cast Eloquent)
        $raw = $produit->getAttributes()['image'] ?? null;

        if (empty($raw)) {
            return [];
        }

        // Déjà un array (cast Eloquent a fonctionné)
        if (is_array($raw)) {
            return array_values(array_filter($raw));
        }

        // Tentative de décodage JSON : ["path1","path2"]
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded));
        }

        // String simple (ancienne donnée non migrée) : "produits_sport/photo.jpg"
        return [trim($raw)];
    }

    // ══════════════════════════════════════════════════════
    // INDEX — Liste paginée avec filtres
    // ══════════════════════════════════════════════════════

    public function index(Request $request)
    {
        try {
            $query = ProduitMedia::with('typeCategorie')
                ->orderBy('created_at', 'desc');

            // Filtre recherche
            if ($request->filled('search')) {
                $query->where('nom', 'LIKE', '%' . $request->search . '%');
            }

            // Filtre catégorie
            if ($request->filled('categorie')) {
                $query->where('type_categorie_id', $request->categorie);
            }

            // Filtre prix max
            if ($request->filled('prix_max')) {
                $query->where('prix', '<=', $request->prix_max);
            }

            // Filtre promotion
            if ($request->filled('en_promotion') && $request->en_promotion) {
                $query->where('enPromotion', true);
            }

            // Tri
            switch ($request->get('sort')) {
                case 'price_asc':
                    $query->orderBy('prix', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('prix', 'desc');
                    break;
                case 'new':
                    $query->orderBy('created_at', 'desc');
                    break;
            }

            $produits = $query->paginate(10);

            // Normaliser imageUrls pour chaque produit
            $produits->getCollection()->transform(function ($produit) {
                $produit->imageUrls = collect($this->getImagesArray($produit))
                    ->map(fn($path) => asset('storage/' . $path))
                    ->values()
                    ->toArray();
                return $produit;
            });

            $typeCategories = TypeCategorie::all();

            return response()->json(compact('produits', 'typeCategories'));

        } catch (\Exception $e) {
            Log::error('ProduitSportController index: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    // ══════════════════════════════════════════════════════
    // SHOW — Détail d'un produit
    // ══════════════════════════════════════════════════════

    public function show($id)
    {
        try {
            $produit = ProduitMedia::with('typeCategorie')->findOrFail($id);

            $produit->imageUrls = collect($this->getImagesArray($produit))
                ->map(fn($path) => asset('storage/' . $path))
                ->values()
                ->toArray();

            return response()->json($produit);

        } catch (\Exception $e) {
            Log::error('ProduitSportController show: ' . $e->getMessage());
            return response()->json(['message' => 'Produit non trouvé.'], 404);
        }
    }

    // ══════════════════════════════════════════════════════
    // STORE — Création
    // ══════════════════════════════════════════════════════

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nom'               => 'required|string|max:255',
                'description'       => 'nullable|string',
                'prix'              => 'required|numeric|min:0',
                'prixPromo'         => 'nullable|numeric|min:0',
                'stock'             => 'required|integer|min:0',
                'enPromotion'       => 'boolean',
                'type_categorie_id' => 'nullable|exists:type_categories,id',
                'video'             => 'nullable|url|max:500',
                'images'            => 'nullable|array|max:10',
                'images.*'          => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            ]);

            DB::beginTransaction();

            $produit = new ProduitMedia();
            $produit->nom             = $validated['nom'];
            $produit->description     = $validated['description'] ?? null;
            $produit->prix            = $validated['prix'];
            $produit->prixPromo       = $validated['prixPromo'] ?? null;
            $produit->stock           = $validated['stock'];
            $produit->enPromotion     = $request->boolean('enPromotion');
            $produit->type_categorie_id = $validated['type_categorie_id'] ?? null;
            $produit->video           = $validated['video'] ?? null;

            // Traitement des images → toujours sauvegarder comme JSON array
            $imagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    if ($imageFile->isValid()) {
                        $imagePaths[] = $imageFile->store('produits_sport', 'public');
                    }
                }
            }
            // Forcer le stockage en JSON array propre
            $produit->setRawAttributes(
                array_merge($produit->getAttributes(), ['image' => json_encode($imagePaths)])
            );

            $produit->save();

            DB::commit();

            $produit->imageUrls = collect($imagePaths)
                ->map(fn($p) => asset('storage/' . $p))
                ->values()
                ->toArray();

            return response()->json($produit->load('typeCategorie'), 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ProduitSportController store: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la création.'], 500);
        }
    }

    // ══════════════════════════════════════════════════════
    // UPDATE — Mise à jour
    // ══════════════════════════════════════════════════════

    public function update(Request $request, $id)
    {
        try {
            $produit = ProduitMedia::findOrFail($id);

            $validated = $request->validate([
                'nom'                  => 'sometimes|required|string|max:255',
                'description'          => 'nullable|string',
                'prix'                 => 'sometimes|required|numeric|min:0',
                'prixPromo'            => 'nullable|numeric|min:0',
                'stock'                => 'sometimes|required|integer|min:0',
                'enPromotion'          => 'boolean',
                'type_categorie_id'    => 'nullable|exists:type_categories,id',
                'video'                => 'nullable|url|max:500',
                'images'               => 'nullable|array|max:10',
                'images.*'             => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'images_a_supprimer'   => 'nullable|array',
                'images_a_supprimer.*' => 'string',
            ]);

            DB::beginTransaction();

            // Champs simples
            if (isset($validated['nom']))                         $produit->nom = $validated['nom'];
            if (array_key_exists('description', $validated))     $produit->description = $validated['description'];
            if (isset($validated['prix']))                        $produit->prix = $validated['prix'];
            if (array_key_exists('prixPromo', $validated))       $produit->prixPromo = $validated['prixPromo'];
            if (isset($validated['stock']))                       $produit->stock = $validated['stock'];
            if ($request->has('enPromotion'))                     $produit->enPromotion = $request->boolean('enPromotion');
            if (array_key_exists('type_categorie_id', $validated)) $produit->type_categorie_id = $validated['type_categorie_id'];
            if (array_key_exists('video', $validated))            $produit->video = $validated['video'];

            // ✅ Récupérer les images existantes via le helper (gère tous les formats)
            $imagesActuelles = $this->getImagesArray($produit);

            // Supprimer les images cochées
            if (!empty($validated['images_a_supprimer'])) {
                foreach ($validated['images_a_supprimer'] as $chemin) {
                    if ($chemin && Storage::disk('public')->exists($chemin)) {
                        Storage::disk('public')->delete($chemin);
                    }
                    $imagesActuelles = array_values(
                        array_filter($imagesActuelles, fn($img) => $img !== $chemin)
                    );
                }
            }

            // Ajouter les nouvelles images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    if ($imageFile->isValid()) {
                        $imagesActuelles[] = $imageFile->store('produits_sport', 'public');
                    }
                }
            }

            // ✅ Toujours sauvegarder en JSON array propre
            $produit->setRawAttributes(
                array_merge($produit->getAttributes(), [
                    'image' => json_encode(array_values($imagesActuelles))
                ])
            );

            $produit->save();

            DB::commit();

            $produit->imageUrls = collect($imagesActuelles)
                ->map(fn($p) => asset('storage/' . $p))
                ->values()
                ->toArray();

            return response()->json($produit->load('typeCategorie'));

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ProduitSportController update: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la mise à jour.'], 500);
        }
    }

    // ══════════════════════════════════════════════════════
    // DESTROY — Suppression
    // ══════════════════════════════════════════════════════

    public function destroy($id)
    {
        try {
            $produit = ProduitMedia::findOrFail($id);

            // ✅ Récupérer les images via le helper (gère string simple, JSON, array)
            $images = $this->getImagesArray($produit);

            foreach ($images as $chemin) {
                if ($chemin && Storage::disk('public')->exists($chemin)) {
                    Storage::disk('public')->delete($chemin);
                }
            }

            $produit->delete();

            return response()->json(['message' => 'Produit supprimé avec succès.']);

        } catch (\Exception $e) {
            Log::error('ProduitSportController destroy: ' . $e->getMessage(), [
                'id'    => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Erreur lors de la suppression.',
                'detail'  => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════
    // GET MEDIAS — URLs publiques
    // ══════════════════════════════════════════════════════

    public function getMedias($id)
    {
        try {
            $produit = ProduitMedia::with('typeCategorie')->findOrFail($id);

            $images = collect($this->getImagesArray($produit))->map(fn($chemin) => [
                'type' => 'image',
                'url'  => asset('storage/' . $chemin),
                'path' => $chemin,
            ]);

            $video = $produit->video
                ? [['type' => 'video', 'url' => $produit->video]]
                : [];

            return response()->json([
                'produit' => [
                    'id'            => $produit->id,
                    'nom'           => $produit->nom,
                    'typeCategorie' => $produit->typeCategorie?->nom,
                ],
                'medias' => $images->concat($video)->values(),
            ]);

        } catch (\Exception $e) {
            Log::error('ProduitSportController getMedias: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la récupération des médias.'], 500);
        }
    }

    // ══════════════════════════════════════════════════════
    // FILTER BY TYPE CATEGORIE
    // ══════════════════════════════════════════════════════

    public function filterByTypeCategorie($typeNom)
    {
        try {
            $types = TypeCategorie::where('nom', 'LIKE', "%{$typeNom}%")->get();

            if ($types->isEmpty()) {
                return response()->json([
                    'message'       => 'Aucun type de catégorie trouvé.',
                    'produits'      => [],
                    'typeCategorie' => null,
                ], 404);
            }

            $typeIds = $types->pluck('id');

            $produits = ProduitMedia::with('typeCategorie')
                ->whereIn('type_categorie_id', $typeIds)
                ->where('stock', '>', 0)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            $produits->getCollection()->transform(function ($produit) {
                $produit->imageUrls = collect($this->getImagesArray($produit))
                    ->map(fn($path) => asset('storage/' . $path))
                    ->values()
                    ->toArray();
                return $produit;
            });

            $typeCategorie = $types->first();

            return response()->json(compact('produits', 'typeCategorie'));

        } catch (\Exception $e) {
            Log::error('ProduitSportController filterByTypeCategorie: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}