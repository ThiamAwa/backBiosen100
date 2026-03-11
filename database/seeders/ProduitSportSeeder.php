<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProduitMedia;
use App\Models\TypeCategorie;

class ProduitSportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Récupérer ou créer le type "Sport"
        $typeSport = TypeCategorie::firstOrCreate(
            ['nom' => 'Sport'],
            ['nom' => 'Sport']
        );
    
        ProduitMedia::create([
            'nom' => 'T-shirt Sport',
            'description' => 'T-shirt confortable pour le sport',
            'prix' => 15000,
            'stock' => 20,
            'type_categorie_id' => $typeSport->id,
            'image' => json_encode([]),
        ]);
    }
}
