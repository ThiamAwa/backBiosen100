<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temoignage extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'nom_client', 'gamme_id',
        'description', 'video_url', 'images', 'afficher',
    ];

    protected $casts = [
        'images'   => 'array',
        'afficher' => 'boolean',
    ];

    public function user()  { return $this->belongsTo(User::class); }
    public function gamme() { return $this->belongsTo(Gamme::class); }

    public function getNomCompletAttribute(): string
    {
        if ($this->user_id && $this->user) {
            return $this->user->prenom . ' ' . $this->user->nom;
        }
        return $this->nom_client ?? 'Anonyme';
    }
}
