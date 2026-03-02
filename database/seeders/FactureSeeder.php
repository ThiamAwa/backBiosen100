<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FactureSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer les commandes existantes avec leurs users
        $commandes = DB::table('commandes')
            ->join('users', 'users.id', '=', 'commandes.user_id')
            ->select('commandes.*', 'users.nom', 'users.prenom', 'users.email', 'users.adresse')
            ->limit(10)
            ->get();

        foreach ($commandes as $index => $commande) {
            DB::table('factures')->insert([
                'commande_id'      => $commande->id,
                'numero_facture'   => 'FAC-' . date('Y') . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'date_emission'    => Carbon::now(),
                'date_echeance'    => Carbon::now()->addDays(30),
                'statut_paiement'  => collect(['payé', 'impayé', 'en_attente'])->random(),
                'metadonnees'      => json_encode([
                    'client' => [
                        'nom'     => trim($commande->prenom . ' ' . $commande->nom),
                        'email'   => $commande->email,
                        'adresse' => $commande->adresse ?? 'Non renseignée',
                    ],
                    'produits' => $this->getProduits($commande->id),
                ]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    private function getProduits(int $commandeId): array
    {
        // Récupère les lignes du panier si elles existent
        $lignes = DB::table('lignes_panier')
            ->join('paniers', 'paniers.id', '=', 'lignes_panier.panier_id')
            ->join('commandes', 'commandes.panier_id', '=', 'paniers.id')
            ->where('commandes.id', $commandeId)
            ->select('lignes_panier.*')
            ->get();

        if ($lignes->isEmpty()) {
            // Données fictives si pas de panier
            return [
                ['nom' => 'Produit A', 'quantite' => 2, 'prix' => 15000],
                ['nom' => 'Produit B', 'quantite' => 1, 'prix' => 25000],
            ];
        }

        return $lignes->map(fn($l) => [
            'nom'      => $l->nom ?? 'Produit',
            'quantite' => $l->quantite,
            'prix'     => $l->prix_unitaire,
        ])->toArray();
    }
}