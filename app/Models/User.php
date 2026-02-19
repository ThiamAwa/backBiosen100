<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nom', 'prenom', 'telephone', 'adresse', 'email',
        'password', 'role_id', 'boutique_id', 'password_change_required',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'password'                 => 'hashed',
            'password_change_required' => 'boolean',
        ];
    }

    public function role()      { return $this->belongsTo(Role::class); }
    public function boutique()  { return $this->belongsTo(Boutique::class); }
    public function paniers()   { return $this->hasMany(Panier::class); }
    public function commandes() { return $this->hasMany(Commande::class); }
    public function paiements() { return $this->hasMany(Paiement::class); }
    public function livraisons(){ return $this->hasMany(Livraison::class); }
    public function notifications() { return $this->hasMany(Notification::class); }
    public function avis()      { return $this->hasMany(Avis::class); }

    public function hasRole(string $role): bool { return $this->role?->name === $role; }
    public function isAdmin(): bool { return in_array($this->role?->name, ['Admin', 'Super Admin']); }
    public function isClient(): bool { return $this->role?->name === 'Client'; }
}
