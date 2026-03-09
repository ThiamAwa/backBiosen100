<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    protected $fillable = [
        'type', 'message', 'lu', 'user_id',
        'produit_id', 'paiement_id', 'commande_id', 'livraison_id',
    ];

    protected $casts = ['lu' => 'boolean'];

    public function user()      { return $this->belongsTo(User::class); }
    public function produit()   { return $this->belongsTo(Produit::class); }
    public function paiement()  { return $this->belongsTo(Paiement::class); }
    public function commande()  { return $this->belongsTo(Commande::class); }
    public function livraison() { return $this->belongsTo(Livraison::class); }
}
