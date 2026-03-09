<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nom', 'prenom', 'telephone', 'adresse', 'email',
        'password', 'role_id', 'boutique_id', 
        'password_change_required',
        'statut',                    // ← ajouter
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'password'                 => 'hashed',
            'password_change_required' => 'boolean',
            'statut'                   => 'string',  // ← ajouter
        ];
    }

    // ← Méthodes obligatoires JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    // Relations
    public function role()          { return $this->belongsTo(Role::class); }
    public function boutique()      { return $this->belongsTo(Boutique::class); }
    public function paniers()       { return $this->hasMany(Panier::class); }
    public function commandes()     { return $this->hasMany(Commande::class); }
    public function paiements()     { return $this->hasMany(Paiement::class); }
    public function livraisons()    { return $this->hasMany(Livraison::class); }
    public function notifications() { return $this->hasMany(Notification::class); }
    public function avis()          { return $this->hasMany(Avis::class); }

    // Helpers rôles
    public function hasRole(string $role): bool { return $this->role?->name === $role; }
    public function isAdmin(): bool  { return in_array($this->role?->name, ['Admin', 'Super Admin']); }
    public function isClient(): bool { return $this->role?->name === 'Client'; }

    // Helpers statut              // ← ajouter
    public function isActif(): bool    { return $this->statut === 'actif'; }
    public function isSuspendu(): bool { return $this->statut === 'suspendu'; }
    public function suspendre(): void  { $this->update(['statut' => 'suspendu']); }
    public function activer(): void    { $this->update(['statut' => 'actif']); }
}