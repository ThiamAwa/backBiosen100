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
    public function index()
    {
        $roleClient = Role::where('name', 'Client')->first();
        $clients = User::with('role')
            ->when($roleClient, fn($q) => $q->where('role_id', $roleClient->id))
            ->orderBy('created_at', 'desc')
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
            'role_id'  => $roleClient->id,
            'password' => Hash::make($validated['password']),
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
        if (!empty($validated['password'])) $data['password'] = Hash::make($validated['password']);

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

    public function stats(User $client)
    {
        return response()->json([
            'total_commandes'   => $client->commandes()->count(),
            'commandes_en_cours'=> $client->commandes()->where('statut', 'en_cours')->count(),
            'commandes_livrees' => $client->commandes()->where('statut', 'valider')->count(),
            'total_depense'     => $client->commandes()->sum('montantTotal'),
            'total_avis'        => $client->avis()->count(),
            'moyenne_avis'      => round($client->avis()->avg('note'), 1),
        ]);
    }
}
