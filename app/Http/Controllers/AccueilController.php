<?php

namespace App\Http\Controllers;


use App\Models\Produit;
use App\Models\Categorie;
use App\Models\Gamme;
use App\Models\TypeCategorie;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccueilController extends Controller
{
    public function index()
    {
        try {
            $produits = Produit::with(['categorie', 'gammes'])
                ->where('stock', '>', 0)
                ->orderBy('created_at', 'desc')
                ->take(12)->get();

            $produitsPromo = Produit::with(['categorie', 'gammes'])
                ->where('enPromotion', true)
                ->whereNotNull('prixPromo')
                ->where('prixPromo', '>', 0)
                ->where('stock', '>', 0)
                ->orderBy('created_at', 'desc')
                ->take(8)->get();

            $gammes = Gamme::with('typeCategorie')->orderBy('nom')->get();
            $categories = Categorie::with('typeCategorie')->orderBy('nom')->get();
            $typeCategories = TypeCategorie::with('categories')->orderBy('nom')->get();

            $vendeurs = collect([]);
            $roleVendeur = Role::where('name', 'Vendeur')->first();
            if ($roleVendeur) {
                $vendeurs = User::where('role_id', $roleVendeur->id)
                    ->select('id', 'nom', 'prenom', 'telephone')
                    ->whereNotNull('telephone')
                    ->orderBy('nom')->get();
            }

            $stats = [
                'total_produits'   => Produit::count(),
                'total_gammes'     => Gamme::count(),
                'total_categories' => Categorie::count(),
                'produits_promo'   => Produit::where('enPromotion', true)->count(),
            ];

            return response()->json(compact(
                'produits', 'produitsPromo', 'gammes',
                'categories', 'typeCategories', 'vendeurs', 'stats'
            ));

        } catch (\Exception $e) {
            Log::error('Accueil index: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur.'], 500);
        }
    }

    public function search(Request $request)
    {
        $query = $request->input('q');
        if (empty($query)) {
            return response()->json(['message' => 'Requête vide.'], 400);
        }

        $produits = Produit::with(['categorie', 'gammes'])
            ->where(function ($q) use ($query) {
                $q->where('nom', 'LIKE', "%{$query}%")
                    ->orWhere('description', 'LIKE', "%{$query}%");
            })
            ->where('stock', '>', 0)
            ->paginate(12);

        return response()->json($produits);
    }

    public function filterByCategorie($categorieId)
    {
        $categorie = Categorie::findOrFail($categorieId);
        $produits = Produit::with(['categorie', 'gammes'])
            ->where('categorie_id', $categorieId)
            ->where('stock', '>', 0)
            ->paginate(12);

        return response()->json(compact('produits', 'categorie'));
    }

    public function filterByGamme($gammeId)
    {
        $gamme = Gamme::findOrFail($gammeId);
        $produits = $gamme->produits()
            ->with(['categorie', 'gammes'])
            ->where('stock', '>', 0)
            ->paginate(12);

        return response()->json(compact('produits', 'gamme'));
    }
}
