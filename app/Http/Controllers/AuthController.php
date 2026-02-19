<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('role', 'boutique')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email ou mot de passe incorrect.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'                       => $user->id,
                'nom'                      => $user->nom,
                'prenom'                   => $user->prenom,
                'email'                    => $user->email,
                'telephone'                => $user->telephone,
                'adresse'                  => $user->adresse,
                'role'                     => $user->role?->name,
                'boutique_id'              => $user->boutique_id,
                'boutique'                 => $user->boutique,
                'password_change_required' => $user->password_change_required,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté avec succès.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('role', 'boutique'));
    }
}
