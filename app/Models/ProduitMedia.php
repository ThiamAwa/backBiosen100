<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProduitMedia extends Model
{
    use HasFactory;

    protected $table = 'produit_medias';

    protected $fillable = [
        'image', 'video', 'nom', 'description', 'prix', 'stock',
        'prixPromo', 'enPromotion', 'noteProduit', 'ordre', 'type_categorie_id',
    ];

    protected $casts = [
        'image'       => 'array',   
        'enPromotion' => 'boolean',
        'ordre'       => 'integer',
        'prix'        => 'decimal:2',
        'prixPromo'   => 'decimal:2',
        'noteProduit' => 'decimal:1',
       
    ];

    public function typeCategorie()
    {
        return $this->belongsTo(TypeCategorie::class);
    }
}
