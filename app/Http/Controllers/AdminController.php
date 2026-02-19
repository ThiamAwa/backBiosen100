<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\User;
use App\Models\Produit;
use App\Models\Gamme;
use App\Models\Categorie;
use App\Models\Boutique;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_commandes'  => Commande::count(),
            'commandes_mois'   => Commande::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'revenus_total'    => Commande::sum('montantTotal'),
            'revenus_mois'     => Commande::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('montantTotal'),
            'total_clients'    => User::whereHas('role', fn($q) => $q->where('name', 'Client'))->count(),
            'clients_mois'     => User::whereHas('role', fn($q) => $q->where('name', 'Client'))->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'taux_conversion'  => $this->calculerTauxConversion(),
        ];

        $stats_produits = [
            'total_produits'    => Produit::count(),
            'total_gammes'      => Gamme::count(),
            'total_categories'  => Categorie::count(),
            'produits_stock_bas'=> Produit::where('stock', '<', 10)->count(),
        ];

        $stats_personnel = [
            'total_boutiques'   => Boutique::count(),
            'total_vendeurs'    => User::whereHas('role', fn($q) => $q->where('name', 'Vendeur'))->count(),
            'total_commerciaux' => User::whereHas('role', fn($q) => $q->where('name', 'Commercial'))->count(),
            'total_responsables'=> User::whereHas('role', fn($q) => $q->where('name', 'Responsable Commercial'))->count(),
        ];

        $commandes_recentes = Commande::with('user')->orderBy('created_at', 'desc')->limit(5)->get();

        $repartition_statuts = Commande::select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')->get();

        $ventes_mensuelles = $this->getVentesMensuelles();

        return response()->json(compact(
            'stats', 'stats_produits', 'stats_personnel',
            'commandes_recentes', 'repartition_statuts', 'ventes_mensuelles'
        ));
    }

    private function calculerTauxConversion(): float
    {
        $total = User::whereHas('role', fn($q) => $q->where('name', 'Client'))->count();
        if ($total === 0) return 0;
        $avecCommande = User::whereHas('role', fn($q) => $q->where('name', 'Client'))->whereHas('commandes')->count();
        return round(($avecCommande / $total) * 100, 1);
    }

    private function getVentesMensuelles(): array
    {
        $moisFr = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
        $ventes = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $ventes[] = [
                'mois'   => $moisFr[$date->month - 1],
                'montant'=> Commande::whereYear('created_at', $date->year)->whereMonth('created_at', $date->month)->sum('montantTotal') ?? 0,
            ];
        }
        return $ventes;
    }
}
