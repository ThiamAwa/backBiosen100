<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gamme extends Model
{
    use HasFactory;
    protected $fillable = [
        'image', 'video', 'nom', 'description', 'modeUtilisation',
        'prix', 'enPromotion', 'prixPromo', 'stock', 'type_categorie_id',
    ];

    protected $casts = ['enPromotion' => 'boolean'];

    public function typeCategorie() { return $this->belongsTo(TypeCategorie::class); }
    public function avis()          { return $this->hasMany(Avis::class); }
    public function temoignages()   { return $this->hasMany(Temoignage::class); }
    public function produits()
    {
        return $this->belongsToMany(Produit::class, 'gamme_produit')->withTimestamps();
    }
}
