<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    use HasFactory;
    protected $fillable = [
        'montant', 'statutPaiement', 'telephone',
        'methodePaiement', 'commande_id', 'user_id', 'livraison_id',
    ];

    public function commande()  { return $this->belongsTo(Commande::class); }
    public function user()      { return $this->belongsTo(User::class); }
    public function livraison() { return $this->belongsTo(Livraison::class); }
}
