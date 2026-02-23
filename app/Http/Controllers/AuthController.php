<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

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
}
