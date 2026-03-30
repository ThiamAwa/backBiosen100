<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\Role;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect.',
                'statut'  => false,
            ], 401);
        }

        try {
            if (!$token = JWTAuth::fromUser($user)) {
                return response()->json([
                    'message' => 'Erreur lors de la génération du token.',
                ], 500);
            }
        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Impossible de créer le token : ' . $e->getMessage(),
            ], 500);
        }

        $user->load('role', 'boutique');

        return response()->json([
            'message' => 'Connexion réussie.',
            'statut'  => true,
            'token'   => $token,
            'user'    => [
                'id'          => $user->id,
                'nom'         => $user->nom,
                'prenom'      => $user->prenom,
                'email'       => $user->email,
                'telephone'   => $user->telephone,
                'boutique_id' => $user->boutique_id,
                'boutique'    => $user->boutique,
                'role'        => $user->role?->name,
            ],
        ], 200);
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json([
                'statut'  => true,
                'message' => 'Déconnecté avec succès.',
                'token'   => null,
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Erreur lors de la déconnexion.',
            ], 500);
        }
    }

    public function me(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            return response()->json($user->load('role', 'boutique'));
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalide.'], 401);
        }
    }

    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return response()->json([
                'token' => $token,
            ]);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token expiré.'], 401);
        }
    }


    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom'       => 'required|string|max:255',
            'prenom'    => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'telephone' => 'required|string|max:20',
            'password'  => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'statut' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Récupérer automatiquement le rôle "Client"
        $roleClient = Role::where('name', 'Client')->first();

        if (!$roleClient) {
            return response()->json([
                'statut'  => false,
                'message' => 'Rôle Client introuvable. Contactez un administrateur.'
            ], 500);
        }

        $user = User::create([
            'nom'       => $request->nom,
            'prenom'    => $request->prenom,
            'email'     => $request->email,
            'telephone' => $request->telephone,
            'password'  => Hash::make($request->password),
            'role_id'   => $roleClient->id,
            'statut'    => 'actif',
        ]);

        // Générer le token JWT directement
        $token = JWTAuth::fromUser($user);
        $user->load('role');

        return response()->json([
            'statut'  => true,
            'message' => 'Compte créé avec succès.',
            'token'   => $token,
            'user'    => [
                'id'        => $user->id,
                'nom'       => $user->nom,
                'prenom'    => $user->prenom,
                'email'     => $user->email,
                'telephone' => $user->telephone,
                'role'      => $user->role?->name,
            ],
        ], 201);
    }
}
