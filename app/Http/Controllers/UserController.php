<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class UserController extends Controller
{
    /**
     * Récupérer le token depuis la requête
     */
    private function getAuthenticatedUser()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return null;
            }
            
            return $user;
            
        } catch (JWTException $e) {
            return null;
        }
    }

    /**
     * Récupérer le profil de l'utilisateur connecté
     */
    public function profile(Request $request)
    {
        $user = $this->getAuthenticatedUser();
        
        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }
        
        // Charger les relations nécessaires
        $user->load('role', 'boutique');
        
        // Ajouter le nombre de commandes
        $commandesCount = Commande::where('user_id', $user->id)->count();
        
        return response()->json([
            'id' => $user->id,
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'email' => $user->email,
            'telephone' => $user->telephone,
            'adresse' => $user->adresse,
            'date_naissance' => $user->date_naissance,
            'genre' => $user->genre,
            'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'newsletter' => $user->newsletter ?? false,
            'role' => $user->role?->name,
            'boutique' => $user->boutique,
            'commandes_count' => $commandesCount,
            'created_at' => $user->created_at->format('d/m/Y'),
        ]);
    }

    /**
     * Mettre à jour le profil
     */
    public function updateProfile(Request $request)
    {
        $user = $this->getAuthenticatedUser();
        
        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'telephone' => 'sometimes|string|max:20',
            'adresse' => 'nullable|string|max:500',
            'date_naissance' => 'nullable|date',
            'genre' => 'nullable|in:homme,femme,autre',
            'newsletter' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Mettre à jour uniquement les champs fournis
        $user->fill($request->only([
            'nom', 'prenom', 'telephone', 'adresse', 
            'date_naissance', 'genre', 'newsletter'
        ]));

        $user->save();

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
                'telephone' => $user->telephone,
                'adresse' => $user->adresse,
                'date_naissance' => $user->date_naissance,
                'genre' => $user->genre,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'newsletter' => $user->newsletter,
            ]
        ]);
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request)
    {
        $user = $this->getAuthenticatedUser();
        
        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier l'ancien mot de passe
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'errors' => [
                    'current_password' => ['Le mot de passe actuel est incorrect']
                ]
            ], 422);
        }

        // Mettre à jour le mot de passe
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Mot de passe modifié avec succès'
        ]);
    }

    /**
     * Uploader un avatar
     */
    public function uploadAvatar(Request $request)
    {
        $user = $this->getAuthenticatedUser();
        
        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Supprimer l'ancien avatar s'il existe
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Uploader le nouveau fichier
        $path = $request->file('avatar')->store('avatars', 'public');
        
        $user->avatar = $path;
        $user->save();

        return response()->json([
            'message' => 'Avatar uploadé avec succès',
            'avatar_url' => asset('storage/' . $path)
        ]);
    }

    /**
     * Supprimer l'avatar
     */
    public function deleteAvatar(Request $request)
    {
        $user = $this->getAuthenticatedUser();
        
        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = null;
        $user->save();

        return response()->json([
            'message' => 'Avatar supprimé avec succès'
        ]);
    }

    /**
     * Récupérer les commandes de l'utilisateur
     */
    public function orders(Request $request)
    {
        $user = $this->getAuthenticatedUser();
        
        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $commandes = Commande::where('user_id', $user->id)
            ->with(['livraison'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($commande) {
                // Décoder les produits (stockés en JSON)
                $produits = is_string($commande->produits) 
                    ? json_decode($commande->produits, true) 
                    : $commande->produits;

                return [
                    'id' => $commande->id,
                    'numeroCommande' => $commande->numeroCommande,
                    'montantTotal' => $commande->montantTotal,
                    'statut' => $commande->statut,
                    'created_at' => $commande->created_at->format('d/m/Y H:i'),
                    'produits_count' => is_array($produits) ? count($produits) : 0,
                    'livraison' => $commande->livraison ? [
                        'statut' => $commande->livraison->statut,
                        'frais' => $commande->livraison->frais,
                    ] : null,
                ];
            });

        return response()->json($commandes);
    }

    /**
     * Récupérer les détails d'une commande spécifique
     */
    public function orderDetails(Request $request, $orderId)
    {
        $user = $this->getAuthenticatedUser();
        
        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $commande = Commande::where('user_id', $user->id)
            ->where('id', $orderId)
            ->with(['livraison'])
            ->first();

        if (!$commande) {
            return response()->json([
                'message' => 'Commande non trouvée'
            ], 404);
        }

        // Décoder les produits
        $produits = is_string($commande->produits) 
            ? json_decode($commande->produits, true) 
            : $commande->produits;

        // Formater les produits
        $produitsFormates = [];
        if (!empty($produits)) {
            foreach ($produits as $produit) {
                $produitsFormates[] = [
                    'nom' => $produit['nom'] ?? 'Produit',
                    'quantite' => $produit['quantite'] ?? 1,
                    'prix_unitaire' => $produit['prix_unitaire'] ?? 0,
                    'total' => ($produit['prix_unitaire'] ?? 0) * ($produit['quantite'] ?? 1),
                    'image' => isset($produit['image']) ? asset('storage/' . $produit['image']) : null,
                ];
            }
        }

        return response()->json([
            'commande' => [
                'id' => $commande->id,
                'numeroCommande' => $commande->numeroCommande,
                'montantTotal' => $commande->montantTotal,
                'statut' => $commande->statut,
                'created_at' => $commande->created_at->format('d/m/Y H:i'),
                'note' => $commande->noteCommande,
                'methode_paiement' => $commande->methode_paiement,
            ],
            'livraison' => $commande->livraison ? [
                'adresse' => $commande->livraison->adresse,
                'telephone' => $commande->livraison->telephone,
                'frais' => $commande->livraison->frais,
                'statut' => $commande->livraison->statut,
                'pays' => $commande->livraison->pays,
            ] : null,
            'produits' => $produitsFormates,
        ]);
    }

    /**
     * Supprimer le compte utilisateur
     */
    public function deleteAccount(Request $request)
    {
        $user = $this->getAuthenticatedUser();
        
        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        // Vérifier si l'utilisateur a des commandes en cours
        $hasPendingOrders = Commande::where('user_id', $user->id)
            ->whereIn('statut', ['en_attente', 'confirmée'])
            ->exists();

        if ($hasPendingOrders) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre compte car vous avez des commandes en cours'
            ], 400);
        }

        // Supprimer l'avatar s'il existe
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Supprimer l'utilisateur
        $user->delete();

        return response()->json([
            'message' => 'Compte supprimé avec succès'
        ]);
    }
}