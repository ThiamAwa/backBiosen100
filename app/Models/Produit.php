<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    use HasFactory;
    protected $fillable = [
        'image', 'video', 'nom', 'description', 'prix', 'stock',
        'prixPromo', 'modeUtilisation', 'enPromotion', 'noteProduit', 'categorie_id',
    ];

    protected $casts = ['enPromotion' => 'boolean'];

    public function categorie()  { return $this->belongsTo(Categorie::class); }
    public function paniers()    { return $this->hasMany(Panier::class); }
    public function avis()       { return $this->hasMany(Avis::class); }
    public function gammes()
    {
        return $this->belongsToMany(Gamme::class, 'gamme_produit')->withTimestamps();
    }

    // Médias
    public function medias()
    {
        return $this->hasMany(ProduitMedia::class)->orderBy('ordre');
    }
    public function images()
    {
        return $this->hasMany(ProduitMedia::class)->where('type', 'image')->orderBy('ordre');
    }
    public function videos()
    {
        return $this->hasMany(ProduitMedia::class)->where('type', 'video_url')->orderBy('ordre');
    }
    public function mediaPrincipal()
    {
        return $this->hasOne(ProduitMedia::class)->where('est_principal', true);
    }
}

