<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Commande;
use App\Models\User;
use App\Models\Panier;
use Carbon\Carbon;

class CommandesTableSeeder extends Seeder
{
    public function run()
    {
        echo "=== Début du seeder de commandes ===\n\n";
        
        // 1. Nettoyer les tables existantes (optionnel)
        Commande::truncate();
        Panier::truncate();
        
        // 2. Vérifier/Créer les utilisateurs
        $users = User::all();
        if ($users->isEmpty()) {
            echo "Création de 3 utilisateurs de test...\n";
            $users = User::factory(3)->create([
                'nom' => fn($faker) => $faker->name,
                'email' => fn($faker) => $faker->unique()->safeEmail,
                'password' => bcrypt('password'),
                'role_id' => 2, // Rôle client
            ]);
        }
        
        echo "Utilisateurs disponibles: " . $users->count() . "\n";
        
        // 3. Données de test pour les commandes
        $commandesData = [
            [
                'numero' => 'CMD20260001',
                'montant' => 125000.50,
                'statut' => 'en_attente',
                'note' => 'Client à rappeler demain pour confirmation',
                'date' => Carbon::now()->subDays(2),
            ],
            [
                'numero' => 'CMD20260002',
                'montant' => 75000.00,
                'statut' => 'en_cours',
                'note' => 'Livraison prévue vendredi',
                'date' => Carbon::now()->subDays(1),
            ],
            [
                'numero' => 'CMD20260003',
                'montant' => 210000.75,
                'statut' => 'valider',
                'note' => 'Commande urgente - traiter en priorité',
                'date' => Carbon::now()->subHours(5),
            ],
            [
                'numero' => 'CMD20260004',
                'montant' => 45000.00,
                'statut' => 'en_attente',
                'note' => null,
                'date' => Carbon::now()->subHours(12),
            ],
            [
                'numero' => 'CMD20260005',
                'montant' => 98000.25,
                'statut' => 'en_cours',
                'note' => 'Client fidèle - offre spéciale',
                'date' => Carbon::now()->subHours(3),
            ],
            [
                'numero' => 'CMD20260006',
                'montant' => 150000.00,
                'statut' => 'valider',
                'note' => 'Commande pour événement',
                'date' => Carbon::now()->subDays(3),
            ],
            [
                'numero' => 'CMD20260007',
                'montant' => 62500.50,
                'statut' => 'en_attente',
                'note' => 'À facturer après validation',
                'date' => Carbon::now()->subDays(4),
            ],
            [
                'numero' => 'CMD20260008',
                'montant' => 112500.00,
                'statut' => 'en_cours',
                'note' => 'Livraison express demandée',
                'date' => Carbon::now()->subHours(8),
            ],
        ];
        
        echo "Création des commandes...\n\n";
        
        foreach ($commandesData as $index => $commandeInfo) {
            try {
                // Sélectionner un utilisateur
                // $user = $users[$index % count($users)];
                
                // 4. Créer le panier (optionnel - 70% de chance)
                // $panierId = null;
                // if (rand(0, 9) < 7) { // 70% de chance d'avoir un panier
                //     $panier = Panier::create([
                //         'user_id' => $user->id,
                //         'quantite' => rand(1, 10),
                //         'created_at' => $commandeInfo['date'],
                //         'updated_at' => $commandeInfo['date'],
                //     ]);
                //     $panierId = $panier->id;
                //     echo "✓ Panier créé (ID: {$panierId}) pour la commande\n";
                // }
                
                // 5. Créer la commande avec les EXACTS noms de colonnes
                $commande = Commande::create([
                    // Colonnes REQUISES (NOT NULL)
                    'numeroCommande' => $commandeInfo['numero'], // Attention: majuscule C
                    'montantTotal' => $commandeInfo['montant'],  // Attention: majuscule T
                    'statut' => $commandeInfo['statut'],
                    
                    // Colonnes avec relations
                    // 'user_id' => $user->id,
                    // 'panier_id' => $panierId, 
                    
                    // Colonne nullable
                    'noteCommande' => $commandeInfo['note'], // Attention: majuscule C
                    
                    // Timestamps
                    'created_at' => $commandeInfo['date'],
                    'updated_at' => $commandeInfo['date'],
                ]);
                
                echo "✅ Commande #{$commandeInfo['numero']} créée avec succès!\n";
                // echo "   Client: {$user->nom}\n";
                echo "   Montant: " . number_format($commandeInfo['montant'], 2) . " FCFA\n";
                echo "   Statut: {$commandeInfo['statut']}\n";
                // echo "   Panier: " . ($panierId ? "Oui (ID: {$panierId})" : "Non") . "\n";
                echo "   Note: " . ($commandeInfo['note'] ?? 'Aucune') . "\n";
                echo "   Date: " . $commandeInfo['date']->format('d/m/Y H:i') . "\n";
                echo "\n";
                
            } catch (\Exception $e) {
                echo "❌ ERREUR pour la commande {$commandeInfo['numero']}:\n";
                echo "   Message: " . $e->getMessage() . "\n";
                
                // Afficher l'erreur SQL détaillée
                if ($e->getPrevious()) {
                    echo "   Erreur SQL: " . $e->getPrevious()->getMessage() . "\n";
                }
                
                echo "\n";
            }
        }
        
        // 6. Résumé
        echo "\n=== RÉSUMÉ ===\n";
        echo "Commandes créées: " . Commande::count() . "\n";
        echo "Paniers créés: " . Panier::count() . "\n";
        
        // 7. Afficher un exemple
        $sample = Commande::with(['user', 'panier'])->first();
        if ($sample) {
            echo "\n📋 Exemple de commande créée:\n";
            echo "ID: {$sample->id}\n";
            echo "Numéro: {$sample->numeroCommande}\n";
            echo "Montant: " . number_format($sample->montantTotal, 2) . " FCFA\n";
            echo "Statut: {$sample->statut}\n";
            // echo "Client: " . ($sample->user ? $sample->user->nom : 'N/A') . "\n";
            // echo "Panier ID: " . ($sample->panier_id ?? 'NULL') . "\n";
            echo "Note: " . ($sample->noteCommande ?? 'Aucune') . "\n";
            echo "Date: " . $sample->created_at->format('d/m/Y H:i') . "\n";
        }
        
        echo "\n=== Seeder terminé avec succès! ===\n";
    }
}