<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Panier;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PanierController extends Controller
{
    public function index()
    {
        $items = Panier::with('produit')
            ->where('user_id', Auth::id())
            ->where('statut', 'actif')
            ->get();

        $total = $items->sum('prixPanier');
        return response()->json(compact('items', 'total'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'produit_id' => 'required|exists:produits,id',
            'quantite'   => 'required|integer|min:1',
        ]);

        $produit = Produit::findOrFail($validated['produit_id']);
        $prix    = $produit->enPromotion && $produit->prixPromo ? $produit->prixPromo : $produit->prix;

        $existant = Panier::where('user_id', Auth::id())
            ->where('produit_id', $produit->id)
            ->where('statut', 'actif')->first();

        if ($existant) {
            $existant->quantite   += $validated['quantite'];
            $existant->prixPanier  = $existant->quantite * $prix;
            $existant->save();
            return response()->json($existant->load('produit'));
        }

        $item = Panier::create([
            'user_id'    => Auth::id(),
            'produit_id' => $produit->id,
            'quantite'   => $validated['quantite'],
            'prixPanier' => $validated['quantite'] * $prix,
            'statut'     => 'actif',
        ]);

        return response()->json($item->load('produit'), 201);
    }

    public function update(Request $request, $id)
    {
        $item = Panier::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $validated = $request->validate(['quantite' => 'required|integer|min:1']);

        $prix             = $item->produit->enPromotion && $item->produit->prixPromo
            ? $item->produit->prixPromo : $item->produit->prix;
        $item->quantite   = $validated['quantite'];
        $item->prixPanier = $validated['quantite'] * $prix;
        $item->save();

        return response()->json($item->load('produit'));
    }

    public function destroy($id)
    {
        $item = Panier::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $item->delete();
        return response()->json(['message' => 'Article supprimé du panier.']);
    }

    public function viderPanier()
    {
        Panier::where('user_id', Auth::id())->where('statut', 'actif')->delete();
        return response()->json(['message' => 'Panier vidé.']);
    }

    public function count()
    {
        $count = Panier::where('user_id', Auth::id())->where('statut', 'actif')->sum('quantite');
        return response()->json(['count' => $count]);
    }
}
