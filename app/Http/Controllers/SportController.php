<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\Categorie;
use App\Models\TypeCategorie;
use Illuminate\Http\Request;

class SportController extends Controller
{
    public function index(Request $request)
    {
        try {
            $typeSport    = TypeCategorie::where('nom', 'Sport')->firstOrFail();
            $categories   = Categorie::where('type_categorie_id', $typeSport->id)->get();
            $categorieIds = $categories->pluck('id');

            $query = Produit::with(['categorie', 'medias'])->whereIn('categorie_id', $categorieIds);

            if ($request->filled('categorie'))  $query->where('categorie_id', $request->categorie);
            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(fn($q) => $q->where('nom', 'like', "%$s%")->orWhere('description', 'like', "%$s%"));
            }
            if ($request->filled('prix_max'))    $query->where('prix', '<=', $request->prix_max);
            if ($request->filled('en_promotion'))$query->where('enPromotion', true);

            $produits = $query->orderBy('created_at', 'desc')->paginate(9)->withQueryString();

            $base  = Produit::whereIn('categorie_id', $categorieIds);
            $stats = [
                'total'            => $base->count(),
                'prix_max'         => $base->max('prix') ?? 50000,
                'categories_count' => $categories->mapWithKeys(
                    fn($c) => [$c->id => Produit::where('categorie_id', $c->id)->count()]
                )->toArray(),
            ];

            return response()->json(compact('produits', 'categories', 'stats'));

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
