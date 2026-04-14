<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Boutique extends Model
{
    use HasFactory;

    protected $table = 'boutiques';

    // Ajoutez 'image' dans fillable
    protected $fillable = ['nom', 'adresse', 'localisation', 'image'];

    // Ajoutez l'accesseur pour image_url
    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
{
    return $this->image ? url('storage/' . $this->image) : null;
}

    // Le reste de vos méthodes...
    public function getFullAddressAttribute(): string
    {
        return "{$this->adresse}, {$this->localisation}";
    }

    public function scopeByLocalisation($query, $localisation)
    {
        return $query->where('localisation', 'like', "%{$localisation}%");
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nom', 'like', "%{$search}%")
                ->orWhere('adresse', 'like', "%{$search}%")
                ->orWhere('localisation', 'like', "%{$search}%");
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function countPersonnelByRole($roleName): int
    {
        return $this->users()
            ->whereHas('role', fn($q) => $q->where('name', $roleName))
            ->count();
    }

    public function getTotalPersonnelAttribute(): int
    {
        return $this->users()
            ->whereHas('role', fn($q) => $q->whereIn('name', ['Vendeur', 'Commercial', 'Responsable Commercial']))
            ->count();
    }

    public function commandes()
    {
        return $this->hasMany(Commande::class);
    }
}