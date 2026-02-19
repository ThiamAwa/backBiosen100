<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Livraison extends Model
{
    use HasFactory;
    protected $fillable = [
        'zone', 'statut', 'telephone', 'frais',
        'pays', 'adresse', 'nom_client', 'prenom_client',
        'commande_id', 'user_id',
    ];

    public function commande() { return $this->belongsTo(Commande::class); }
    public function user()     { return $this->belongsTo(User::class); }
    public function paiements(){ return $this->hasMany(Paiement::class); }
}
