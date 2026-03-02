<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commande extends Model
{
    use HasFactory;
    protected $fillable = [
        'numeroCommande', 'montantTotal', 'user_id', 'panier_id',
        'boutique_id',  
        'noteCommande', 'statut', 'email', 'nom_client', 'prenom_client',
        'telephone_client', 'adresse_client', 'pays', 'ville_zone',
        'code_postal', 'region', 'methode_paiement', 'is_guest',
    ];

    protected $casts = ['is_guest' => 'boolean'];

    public function user()     { return $this->belongsTo(User::class); }
    public function panier()   { return $this->belongsTo(Panier::class); }
    public function paiement() { return $this->hasOne(Paiement::class); }
    public function livraison(){ return $this->hasOne(Livraison::class); }
    public function notifications() { return $this->hasMany(Notification::class); }
    public function boutique()
    {
        return $this->belongsTo(Boutique::class);
    }

    public function factures()
{
    return $this->hasMany(Facture::class);
}
}

