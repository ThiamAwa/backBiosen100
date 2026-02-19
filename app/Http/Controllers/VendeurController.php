<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Boutique;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class VendeurController extends Controller
{
    private function getRolesCommerciaux()
    {
        return Role::whereIn('name', ['Vendeur', 'Commercial', 'Responsable Commercial'])->get();
    }

    public function index()
    {
        $rolesIds = $this->getRolesCommerciaux()->pluck('id');
        $vendeurs = User::whereIn('role_id', $rolesIds)
            ->with(['role', 'boutique'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        $roles    = $this->getRolesCommerciaux();
        $boutiques= Boutique::orderBy('nom')->get();
        return response()->json(compact('vendeurs', 'roles', 'boutiques'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'        => 'required|string|max:255',
            'prenom'     => 'nullable|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'telephone'  => 'required|string|max:20',
            'adresse'    => 'required|string|max:255',
            'password'   => 'required|string|min:8',
            'role_id'    => 'required|exists:roles,id',
            'boutique_id'=> 'nullable|exists:boutiques,id',
        ]);

        $role = $this->getRolesCommerciaux()->find($validated['role_id']);
        if (!$role) return response()->json(['message' => 'Rôle commercial invalide.'], 422);

        $vendeur = User::create([
            ...$validated,
            'password'            => Hash::make($validated['password']),
            'password_change_required' => true,
        ]);
        return response()->json($vendeur->load(['role', 'boutique']), 201);
    }

    public function update(Request $request, $id)
    {
        $vendeur   = User::findOrFail($id);
        $validated = $request->validate([
            'nom'        => 'required|string|max:255',
            'prenom'     => 'nullable|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $id,
            'telephone'  => 'required|string|max:20',
            'adresse'    => 'required|string|max:255',
            'password'   => 'nullable|string|min:8',
            'role_id'    => 'required|exists:roles,id',
            'boutique_id'=> 'nullable|exists:boutiques,id',
        ]);

        $role = $this->getRolesCommerciaux()->find($validated['role_id']);
        if (!$role) return response()->json(['message' => 'Rôle commercial invalide.'], 422);

        $data = collect($validated)->except('password')->toArray();
        if (!empty($validated['password'])) $data['password'] = Hash::make($validated['password']);

        $vendeur->update($data);
        return response()->json($vendeur->load(['role', 'boutique']));
    }

    public function changeRole(Request $request, $id)
    {
        $vendeur   = User::findOrFail($id);
        $validated = $request->validate(['role_id' => 'required|exists:roles,id']);

        $role = $this->getRolesCommerciaux()->find($validated['role_id']);
        if (!$role) return response()->json(['message' => 'Rôle commercial invalide.'], 422);

        $vendeur->update(['role_id' => $validated['role_id']]);
        return response()->json(['message' => 'Rôle changé : ' . $role->name, 'user' => $vendeur->load('role')]);
    }

    public function destroy($id)
    {
        $vendeur   = User::findOrFail($id);
        $rolesIds  = $this->getRolesCommerciaux()->pluck('id');
        if (!$rolesIds->contains($vendeur->role_id)) {
            return response()->json(['message' => 'Cet utilisateur n\'est pas un commercial.'], 422);
        }
        $vendeur->delete();
        return response()->json(['message' => 'Vendeur supprimé.']);
    }
}
