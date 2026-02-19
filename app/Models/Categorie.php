<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categorie extends Model
{
    use HasFactory;
    protected $fillable = ['nom', 'description', 'type_categorie_id'];

    public function typeCategorie() { return $this->belongsTo(TypeCategorie::class); }
    public function gammes()        { return $this->hasMany(Gamme::class); }
    public function produits()      { return $this->hasMany(Produit::class); }
}
