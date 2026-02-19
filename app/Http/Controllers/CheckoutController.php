<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Livraison;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function process(Request $request)
    {
        $request->validate([
            'nom'            => 'required|string|max:255',
            'prenom'         => 'required|string|max:255',
            'telephone'      => 'required|string|max:20',
            'adresse'        => 'required|string|max:500',
            'email'          => 'nullable|email',
            'cart_data'      => 'required|string',
            'payment_method' => 'required|string',
            'shipping_cost'  => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $cartItems = json_decode($request->cart_data, true);
            if (empty($cartItems)) {
                return response()->json(['message' => 'Le panier est vide.'], 400);
            }

            $user  = Auth::user();
            $total = collect($cartItems)->sum(fn($i) => $i['price'] * $i['quantity']);
            $total += $request->shipping_cost ?? 0;

            // Création de compte si demandé (guest)
            if (!$user && $request->boolean('create_account')) {
                $request->validate([
                    'email'    => 'required|email|unique:users,email',
                    'password' => 'required|min:8',
                ]);
                $roleClient = Role::where('name', 'Client')->firstOrFail();
                $user = User::create([
                    'nom'      => $request->nom,
                    'prenom'   => $request->prenom,
                    'email'    => $request->email,
                    'telephone'=> $request->telephone,
                    'adresse'  => $request->adresse,
                    'password' => Hash::make($request->password),
                    'role_id'  => $roleClient->id,
                ]);
            }

            $commande = Commande::create([
                'numeroCommande'   => 'BIOSEN-' . time() . '-' . strtoupper(Str::random(4)),
                'montantTotal'     => $total,
                'user_id'          => $user?->id,
                'noteCommande'     => $request->notes,
                'statut'           => 'en_attente',
                'email'            => $request->email ?? $user?->email,
                'nom_client'       => $request->nom,
                'prenom_client'    => $request->prenom,
                'telephone_client' => $request->telephone,
                'adresse_client'   => $request->adresse,
                'pays'             => $request->pays,
                'ville_zone'       => $request->zone_livraison ?? $request->ville ?? '',
                'code_postal'      => $request->code_postal,
                'region'           => $request->region,
                'methode_paiement' => $request->payment_method,
                'is_guest'         => !$user,
            ]);

            Livraison::create([
                'zone'        => $request->zone_livraison ?? $request->ville ?? '',
                'statut'      => 'en_attente',
                'telephone'   => $request->telephone,
                'frais'       => $request->shipping_cost ?? 0,
                'pays'        => $request->pays,
                'adresse'     => $request->adresse,
                'nom_client'  => $request->nom,
                'prenom_client'=> $request->prenom,
                'commande_id' => $commande->id,
                'user_id'     => $user?->id,
            ]);

            DB::commit();

            return response()->json([
                'message'      => 'Commande créée avec succès.',
                'order_number' => $commande->numeroCommande,
                'commande_id'  => $commande->id,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function confirmation($orderNumber)
    {
        $commande = Commande::where('numeroCommande', $orderNumber)
            ->with(['user', 'livraison'])->first();

        if (!$commande) return response()->json(['message' => 'Commande non trouvée.'], 404);

        if (Auth::check() && Auth::id() !== $commande->user_id && !Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        return response()->json($commande);
    }
}
