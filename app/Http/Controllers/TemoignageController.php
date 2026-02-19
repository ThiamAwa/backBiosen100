<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Temoignage;
use App\Models\Gamme;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TemoignageController extends Controller
{
    public function index()
    {
        $temoignages = Temoignage::with(['gamme', 'user'])->latest()->paginate(10);
        $gammes      = Gamme::all();
        $roleClient  = Role::where('name', 'Client')->first();
        $clients     = $roleClient ? User::where('role_id', $roleClient->id)->orderBy('nom')->get() : collect();
        return response()->json(compact('temoignages', 'gammes', 'clients'));
    }

    public function showPublic()
    {
        $temoignages = Temoignage::with(['gamme', 'user'])
            ->where('afficher', true)
            ->latest()->get();
        return response()->json($temoignages);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id'     => 'nullable|exists:users,id',
                'nom_client'  => 'nullable|string|max:255',
                'gamme_id'    => 'nullable|exists:gammes,id',
                'description' => 'nullable|string',
                'video_url'   => 'nullable|url',
                'images.*'    => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'afficher'    => 'boolean',
            ]);

            if (empty($validated['user_id']) && empty($validated['nom_client'])) {
                return response()->json(['message' => 'user_id ou nom_client requis.'], 422);
            }

            $validated['afficher'] = $request->boolean('afficher', true);

            if ($request->hasFile('images')) {
                $validated['images'] = collect($request->file('images'))
                    ->map(fn($img) => $img->store('temoignages', 'public'))
                    ->toArray();
            }

            $t = Temoignage::create($validated);
            return response()->json($t->load(['gamme', 'user']), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Temoignage store: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $t         = Temoignage::findOrFail($id);
            $validated = $request->validate([
                'user_id'         => 'nullable|exists:users,id',
                'nom_client'      => 'nullable|string|max:255',
                'gamme_id'        => 'nullable|exists:gammes,id',
                'description'     => 'nullable|string',
                'video_url'       => 'nullable|url',
                'images.*'        => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'afficher'        => 'boolean',
                'supprimer_images'=> 'boolean',
            ]);

            if (empty($validated['user_id']) && empty($validated['nom_client'])) {
                return response()->json(['message' => 'user_id ou nom_client requis.'], 422);
            }

            $validated['afficher'] = $request->boolean('afficher');

            if ($request->boolean('supprimer_images') && $t->images) {
                foreach ($t->images as $img) Storage::disk('public')->delete($img);
                $validated['images'] = null;
            }

            if ($request->hasFile('images')) {
                if ($t->images) foreach ($t->images as $img) Storage::disk('public')->delete($img);
                $validated['images'] = collect($request->file('images'))
                    ->map(fn($img) => $img->store('temoignages', 'public'))
                    ->toArray();
            } else {
                unset($validated['images']);
            }

            $t->update($validated);
            return response()->json($t->load(['gamme', 'user']));

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $t = Temoignage::findOrFail($id);
        if ($t->images) foreach ($t->images as $img) Storage::disk('public')->delete($img);
        $t->delete();
        return response()->json(['message' => 'Témoignage supprimé.']);
    }
}
