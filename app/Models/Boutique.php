<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Boutique extends Model
{
    use HasFactory;
    protected $table = 'boutiques';
    protected $fillable = ['nom', 'adresse', 'localisation'];

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

    public function users() { return $this->hasMany(User::class); }

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
}
