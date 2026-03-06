<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $roleClient = Role::where('name', 'Client')->first();

        $clients = User::with('role')
            ->when($roleClient, fn($q) => $q->where('role_id', $roleClient->id))

            // Recherche par nom, prénom, email, téléphone
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('nom',       'like', '%' . $request->search . '%')
                          ->orWhere('prenom',    'like', '%' . $request->search . '%')
                          ->orWhere('email',     'like', '%' . $request->search . '%')
                          ->orWhere('telephone', 'like', '%' . $request->search . '%');
                });
            })

            // Filtre par statut
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))

            // Tri
            ->when($request->tri === 'depense', function ($q) {
                $q->withSum('commandes', 'montantTotal')
                  ->orderByDesc('commandes_sum_montant_total');
            })
            ->when($request->tri === 'commandes', function ($q) {
                $q->withCount('commandes')
                  ->orderByDesc('commandes_count');
            })
            ->when($request->tri === 'recent' || !$request->tri, 
                fn($q) => $q->orderBy('created_at', 'desc')
            )

            // Stats dans la liste
            ->withCount('commandes')
            ->withSum('commandes', 'montantTotal')

            ->paginate(15);

        return response()->json($clients);
    }

    public function show(User $client)
    {
        return response()->json($client->load(['role', 'commandes', 'avis']));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'       => 'required|string|max:255',
            'prenom'    => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'telephone' => 'required|string|max:20',
            'adresse'   => 'nullable|string|max:500',
            'password'  => ['required', Password::min(8)],
        ]);

        $roleClient = Role::where('name', 'Client')->firstOrFail();

        $client = User::create([
            ...$validated,
            'role_id'           => $roleClient->id,
            'statut'            => 'actif',   // ← statut par défaut
            'password'          => Hash::make($validated['password']),
            'email_verified_at' => $request->boolean('email_verified') ? now() : null,
        ]);

        return response()->json($client->load('role'), 201);
    }

    public function update(Request $request, User $client)
    {
        $validated = $request->validate([
            'nom'       => 'required|string|max:255',
            'prenom'    => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email,' . $client->id,
            'telephone' => 'required|string|max:20',
            'adresse'   => 'nullable|string|max:500',
            'role_id'   => 'required|exists:roles,id',
            'password'  => ['nullable', Password::min(8)],
        ]);

        $data = collect($validated)->except('password')->toArray();
        $data['email_verified_at'] = $request->boolean('email_verified') ? now() : null;

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $client->update($data);

        return response()->json($client->load('role'));
    }

    public function destroy(User $client)
    {
        if ($client->commandes()->exists()) {
            return response()->json(['message' => 'Impossible : ce client a des commandes.'], 422);
        }

        $client->delete();

        return response()->json(['message' => 'Client supprimé.']);
    }

    public function verifyEmail(User $client)
    {
        $client->update(['email_verified_at' => now()]);
        return response()->json(['message' => 'Email vérifié.']);
    }

    // ← Nouvelle méthode : changer le statut
    public function toggleStatut(Request $request, User $client)
    {
        $request->validate([
            'statut' => 'required|in:actif,suspendu',
        ]);

        $client->update(['statut' => $request->statut]);

        return response()->json([
            'message' => $request->statut === 'actif'
                ? 'Client activé avec succès.'
                : 'Client suspendu avec succès.',
            'statut'  => $client->statut,
        ]);
    }

    public function stats(User $client)
    {
        // Dernières commandes
        $dernieres_commandes = $client->commandes()
            ->latest()
            ->take(5)
            ->get([
                'id',
                'reference',
                'created_at as date',
                'montantTotal as montant',
                'statut',
            ]);

        return response()->json([
            'total_commandes'     => $client->commandes()->count(),
            'commandes_en_cours'  => $client->commandes()->where('statut', 'en_cours')->count(),
            'commandes_livrees'   => $client->commandes()->where('statut', 'valider')->count(),
            'total_depense'       => $client->commandes()->sum('montantTotal'),
            'total_avis'          => $client->avis()->count(),
            'moyenne_avis'        => round($client->avis()->avg('note'), 1),
            'dernieres_commandes' => $dernieres_commandes,  // ← ajouter
        ]);
    }
}