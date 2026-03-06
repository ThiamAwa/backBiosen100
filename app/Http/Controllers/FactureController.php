<?php

namespace App\Http\Controllers;

use App\Models\Facture;
use App\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class FactureController extends Controller
{
    /**
     * Liste paginée des factures avec filtres.
     */
    public function index(Request $request)
    {
        $query = Facture::with('commande.user', 'commande.boutique');

        // Filtre par statut de paiement
        if ($request->filled('statut_paiement')) {
            $query->where('statut_paiement', $request->statut_paiement);
        }

        // Filtre par plage de dates
        if ($request->filled('date_debut')) {
            $query->whereDate('date_emission', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('date_emission', '<=', $request->date_fin);
        }

        // Recherche par numéro de facture ou nom client
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero_facture', 'like', "%{$search}%")
                  ->orWhereHas('commande', function ($sub) use ($search) {
                      $sub->where('nom_client', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $factures = $query->orderBy('date_emission', 'desc')->paginate(15);

        return response()->json($factures);
    }

    /**
     * Affiche une facture spécifique.
     */
    public function show(Facture $facture)
    {
        $facture->load('commande.user', 'commande.boutique');
        return response()->json($facture);
    }

    /**
     * Génère une facture pour une commande donnée.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'commande_id' => 'required|exists:commandes,id',
            'statut_paiement' => 'nullable|in:payé,impayé,en_attente',
            'date_echeance'   => 'nullable|date',
        ]);

        $commande = Commande::with(['user', 'panier.lignesPanier.gamme'])->findOrFail($validated['commande_id']);

        // Vérifier si une facture existe déjà
        if (Facture::where('commande_id', $commande->id)->exists()) {
            return response()->json(['message' => 'Une facture existe déjà pour cette commande.'], 422);
        }

        // Calcul des montants (adaptez selon votre logique)
        $montantTotal = $commande->montantTotal;
        $tva = round($montantTotal * 0.18, 2); 
        $montantHT = $montantTotal - $tva;

        // Générer un numéro unique
        $numero = $this->generateInvoiceNumber();

        // Créer la facture
        $facture = Facture::create([
            'commande_id'     => $commande->id,
            'numero_facture'  => $numero,
            'montant_ht'      => $montantHT,
            'taxe'            => $tva,
            'montant_total'   => $montantTotal,
            'date_emission'   => now(),
            'date_echeance'   => $validated['date_echeance'] ?? now()->addDays(30),
            'statut_paiement' => $validated['statut_paiement'] ?? 'en_attente',
            'metadonnees'     => [
                'client' => [
                    'nom'    => $commande->user->nom ?? $commande->nom_client,
                    'email'  => $commande->user->email ?? $commande->email,
                    'adresse'=> $commande->user->adresse ?? $commande->adresse_client,
                ],
                'produits' => $commande->panier ? $commande->panier->lignesPanier->map(function ($ligne) {
                    return [
                        'nom'      => $ligne->gamme->nom ?? 'Produit supprimé',
                        'quantite' => $ligne->quantite,
                        'prix'     => $ligne->prixUnitaire,
                    ];
                }) : [],
            ],
        ]);

        // Générer le PDF
        $pdfPath = $this->generatePdf($facture);
        $facture->update(['chemin_pdf' => $pdfPath]);

        return response()->json($facture->load('commande'), 201);
    }

    /**
     * Met à jour le statut de paiement ou la date d'échéance.
     */
    public function update(Request $request, Facture $facture)
    {
        $validated = $request->validate([
            'statut_paiement' => 'sometimes|in:payé,impayé,en_attente',
            'date_echeance'   => 'sometimes|date',
        ]);

        $facture->update($validated);
        return response()->json($facture);
    }

    /**
     * Télécharge le PDF de la facture.
     */
    // public function download(Facture $facture)
    // {
    //     if (!$facture->chemin_pdf || !Storage::disk('public')->exists($facture->chemin_pdf)) {
    //         return response()->json(['message' => 'Fichier PDF introuvable.'], 404);
    //     }

    //     return Storage::disk('public')->download($facture->chemin_pdf, "facture_{$facture->numero_facture}.pdf");
    // }

    /**
     * Supprime une facture.
     */
    public function destroy(Facture $facture)
    {
        // Supprimer le fichier PDF associé
        if ($facture->chemin_pdf && Storage::disk('public')->exists($facture->chemin_pdf)) {
            Storage::disk('public')->delete($facture->chemin_pdf);
        }
        $facture->delete();

        return response()->json(['message' => 'Facture supprimée.']);
    }

    // =============================
    // Méthodes privées
    // =============================

    /**
     * Génère un numéro de facture unique (format : FAAAAMM-XXXX)
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'F' . date('Y') . date('m'); 
        $lastFacture = Facture::where('numero_facture', 'like', $prefix . '%')
                               ->orderBy('numero_facture', 'desc')
                               ->first();

        if ($lastFacture) {
            $lastNumber = intval(substr($lastFacture->numero_facture, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . '-' . $newNumber;
    }

   
}