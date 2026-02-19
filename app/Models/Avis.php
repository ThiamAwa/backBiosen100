<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Avis extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'produit_id', 'gamme_id',
        'note', 'commentaire', 'image', 'video',
    ];

    public function user()    { return $this->belongsTo(User::class); }
    public function produit() { return $this->belongsTo(Produit::class); }
    public function gamme()   { return $this->belongsTo(Gamme::class); }
}

