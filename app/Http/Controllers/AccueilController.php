<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\ProduitMedia;
use App\Models\Categorie;
use App\Models\Gamme;
use App\Models\TypeCategorie;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccueilController extends Controller
{
    /**
     * Récupère toutes les données nécessaires à la page d'accueil.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Derniers produits génériques
            $produits = Produit::with(['categorie', 'gammes'])
                ->where('stock', '>', 0)
                ->orderBy('created_at', 'desc')
                ->take(12)
                ->get();

            // Produits en promotion
            $produitsPromo = Produit::with(['categorie', 'gammes'])
                ->where('enPromotion', true)
                ->whereNotNull('prixPromo')
                ->where('prixPromo', '>', 0)
                ->where('stock', '>', 0)
                ->orderBy('created_at', 'desc')
                ->take(8)
                ->get();

            // Gammes avec leur type de catégorie
            $gammes = Gamme::with('typeCategorie')->orderBy('nom')->get();

            // Catégories avec leur type de catégorie
            $categories = Categorie::with('typeCategorie')->orderBy('nom')->get();

            // Types de catégories avec leurs catégories enfants
            $typeCategories = TypeCategorie::with('categories')->orderBy('nom')->get();

            // ✅ Produits sport : on récupère d'abord le type "Sport"
            $typeSport = TypeCategorie::where('nom', 'LIKE', '%sport%')->first();
            $produitsSport = collect(); // vide par défaut

            if ($typeSport) {
                $produitsSport = ProduitMedia::with('typeCategorie')
                    ->where('stock', '>', 0)
                    ->where('type_categorie_id', $typeSport->id)
                    ->orderBy('created_at', 'desc')
                    ->take(8)
                    ->get();
            }

            // Récupération des vendeurs
            $vendeurs = collect([]);
            $roleVendeur = Role::where('name', 'Vendeur')->first();
            if ($roleVendeur) {
                $vendeurs = User::where('role_id', $roleVendeur->id)
                    ->select('id', 'nom', 'prenom', 'telephone')
                    ->whereNotNull('telephone')
                    ->orderBy('nom')
                    ->get();
            }

            // Statistiques générales
            $stats = [
                'total_produits'   => Produit::count(),
                'total_gammes'     => Gamme::count(),
                'total_categories' => Categorie::count(),
                'produits_promo'   => Produit::where('enPromotion', true)->count(),
            ];

            return response()->json(compact(
                'produits', 'produitsPromo', 'gammes',
                'categories', 'typeCategories', 'vendeurs',
                'stats', 'produitsSport'
            ));

        } catch (\Exception $e) {
            Log::error('Accueil index: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Recherche de produits génériques par mot-clé.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $query = $request->input('q');
        if (empty($query)) {
            return response()->json(['message' => 'Requête vide.'], 400);
        }

        try {
            $produits = Produit::with(['categorie', 'gammes'])
                ->where(function ($q) use ($query) {
                    $q->where('nom', 'LIKE', "%{$query}%")
                        ->orWhere('description', 'LIKE', "%{$query}%");
                })
                ->where('stock', '>', 0)
                ->paginate(12);

            return response()->json($produits);
        } catch (\Exception $e) {
            Log::error('Accueil search: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Filtre les produits par catégorie (générique).
     *
     * @param  int  $categorieId
     * @return \Illuminate\Http\JsonResponse
     */
    public function filterByCategorie($categorieId)
    {
        try {
            $categorie = Categorie::findOrFail($categorieId);
            $produits = Produit::with(['categorie', 'gammes'])
                ->where('categorie_id', $categorieId)
                ->where('stock', '>', 0)
                ->paginate(12);

            return response()->json(compact('produits', 'categorie'));
        } catch (\Exception $e) {
            Log::error('Accueil filterByCategorie: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Filtre les produits par gamme (générique).
     *
     * @param  int  $gammeId
     * @return \Illuminate\Http\JsonResponse
     */
    public function filterByGamme($gammeId)
    {
        try {
            $gamme = Gamme::findOrFail($gammeId);
            $produits = $gamme->produits()
                ->with(['categorie', 'gammes'])
                ->where('stock', '>', 0)
                ->paginate(12);

            return response()->json(compact('produits', 'gamme'));
        } catch (\Exception $e) {
            Log::error('Accueil filterByGamme: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Filtre les produits génériques par le nom du type de catégorie.
     *
     * @param  string  $typeNom
     * @return \Illuminate\Http\JsonResponse
     */
    public function filterByTypeCategorie($typeNom)
    {
        try {
            $types = TypeCategorie::where('nom', 'LIKE', "%{$typeNom}%")->get();
            if ($types->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun type de catégorie trouvé.',
                    'produits' => [],
                    'typeCategorie' => null
                ], 404);
            }
            $typeIds = $types->pluck('id');
            $produits = Produit::with(['categorie', 'gammes'])
                ->whereHas('categorie', function ($q) use ($typeIds) {
                    $q->whereIn('type_categorie_id', $typeIds);
                })
                ->where('stock', '>', 0)
                ->paginate(12);
            $typeCategorie = $types->first();
            return response()->json(compact('produits', 'typeCategorie'));
        } catch (\Exception $e) {
            Log::error('Accueil filterByTypeCategorie: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }
}