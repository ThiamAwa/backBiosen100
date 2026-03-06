<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FactureSeeder extends Seeder
{
    public function run(): void
    {
        $commandes = DB::table('commandes')->limit(10)->get();

        foreach ($commandes as $index => $commande) {

            DB::table('factures')->insert([
                'commande_id' => $commande->id,
                'numero_facture' => 'FAC-' . date('Y') . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'date_emission' => Carbon::now(),
                'date_echeance' => Carbon::now()->addDays(30),
                'statut_paiement' => collect(['payé','impayé','en_attente'])->random(),
                'metadonnees' => json_encode([
                    'client' => [
                        'nom' => 'Client Test',
                        'email' => 'client@test.com',
                        'adresse' => 'Dakar'
                    ]
                ]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            echo "Facture créée pour la commande ID : ".$commande->id."\n";
        }
    }
}