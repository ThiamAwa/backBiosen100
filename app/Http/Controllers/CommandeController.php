<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use Illuminate\Http\Request;

class CommandeController extends Controller
{
    public function index(Request $request)
    {
        $query = Commande::with(['user', 'livraison', 'paiement', 'boutique']);

        // Filtre par statut
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        // Filtre par boutique
        if ($request->filled('boutique_id')) {
            $query->where('boutique_id', $request->boutique_id);
        }

        // ── Filtres date ──────────────────────────────────────────────────────

        // Date exacte (prioritaire sur mois/année)
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        } else {
            // Filtre par mois
            if ($request->filled('month')) {
                $query->whereMonth('created_at', (int) $request->month);
            }
            // Filtre par année
            if ($request->filled('year')) {
                $query->whereYear('created_at', (int) $request->year);
            }
        }

        // ── Recherche ─────────────────────────────────────────────────────────
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('numeroCommande', 'like', "%{$s}%")
                  ->orWhere('nom_client',   'like', "%{$s}%")
                  ->orWhere('email',        'like', "%{$s}%")
                  ->orWhereHas('user', fn($sq) =>
                      $sq->where('nom',     'like', "%{$s}%")
                         ->orWhere('prenom', 'like', "%{$s}%")
                         ->orWhere('email',  'like', "%{$s}%")
                  )
                  ->orWhereHas('boutique', fn($sq) =>
                      $sq->where('nom', 'like', "%{$s}%")
                  );
            });
        }

        return response()->json(
            $query->orderBy('created_at', 'desc')->paginate(10)
        );
    }

    public function show(Commande $commande)
    {
        return response()->json(
            $commande->load(['user', 'livraison', 'paiement'])
        );
    }

    public function update(Request $request, Commande $commande)
    {
        $validated = $request->validate([
            'statut'       => 'required|in:en_attente,en_cours,valider',
            'noteCommande' => 'nullable|string|max:500',
        ]);

        $commande->update($validated);

        return response()->json($commande);
    }

    public function destroy(Commande $commande)
    {
        $commande->delete();

        return response()->json(['message' => 'Commande supprimée.']);
    }

    public function statistics()
    {
        return response()->json([
            'total'         => Commande::count(),
            'en_attente'    => Commande::where('statut', 'en_attente')->count(),
            'en_cours'      => Commande::where('statut', 'en_cours')->count(),
            'validees'      => Commande::where('statut', 'valider')->count(),
            'montant_total' => Commande::sum('montantTotal'),
            'montant_moyen' => round(Commande::avg('montantTotal'), 2),
        ]);
    }

    public function export(Request $request)
    {
        $commandes = Commande::with(['user', 'livraison'])
            ->when($request->filled('statut'), fn($q) =>
                $q->where('statut', $request->statut)
            )
            ->when($request->filled('date'), fn($q) =>
                $q->whereDate('created_at', $request->date),
            fn($q) =>
                $q->when($request->filled('month'), fn($q) =>
                    $q->whereMonth('created_at', (int) $request->month)
                  )
                  ->when($request->filled('year'), fn($q) =>
                    $q->whereYear('created_at', (int) $request->year)
                  )
            )
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'commandes_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\""
        ];

        $callback = function () use ($commandes) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'N° Commande',
                'Client',
                'Email',
                'Montant (FCFA)',
                'Statut',
                'Date'
            ]);
            foreach ($commandes as $c) {
                fputcsv($file, [
                    $c->numeroCommande,
                    $c->nom_client ?? $c->user?->nom ?? 'Invité',
                    $c->email      ?? $c->user?->email ?? '',
                    $c->montantTotal,
                    $c->statut,
                    $c->created_at->format('d/m/Y H:i'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}