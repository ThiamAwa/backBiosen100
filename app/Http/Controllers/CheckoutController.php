<?php
namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Livraison;
use App\Models\Panier;
use App\Models\User;
use App\Models\Role;
use App\Models\Facture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Tymon\JWTAuth\Facades\JWTAuth;
use Paydunya\Paydunya;
use Paydunya\Checkout\CheckoutInvoice;
use Paydunya\Checkout\Store;

class CheckoutController extends Controller
{
    
// ══════════════════════════════════════════════════════════
// PROCESS CHECKOUT
// ══════════════════════════════════════════════════════════
public function process(Request $request)
{
    try {
        $request->validate([
            'nom'            => 'required|string|max:255',
            'prenom'         => 'required|string|max:255',
            'telephone'      => 'required|string|max:20',
            'pays'           => 'required|string|max:100',
            'adresse'        => 'required|string|max:500',
            'cart_data'      => 'nullable|string',
            'payment_method' => 'nullable|string',
        ]);

        // Validation selon le pays
        if ($request->pays === 'Senegal') {
            $request->validate(['zone_livraison' => 'required|string']);
        } else {
            $request->validate([
                'code_postal' => 'required|string|max:20',
                'ville'       => 'required|string|max:255',
                'region'      => 'required|string|max:255',
            ]);
        }

        // ── Récupération du panier ───────────────────────────────
        if (Auth::check()) {
            // Utilisateur connecté → charger depuis la table paniers
            $paniers = Panier::where('user_id', Auth::id())
                ->where('statut', 'actif')
                ->with('produit')
                ->get();

            if ($paniers->isEmpty()) {
                return response()->json(['message' => 'Le panier est vide.'], 400);
            }

            $cartItems = $paniers->map(function ($panier) {
                return [
                    'id'       => $panier->produit_id,
                    'name'     => $panier->produit->nom ?? 'Produit',
                    'quantity' => $panier->quantite,
                    'price'    => $panier->prixPanier,
                    'image'    => $panier->produit->image ?? null,
                    'category' => $panier->produit->categorie ?? 'Bio',
                    'type'     => $panier->produit->type ?? 'gamme',
                ];
            })->toArray();

        } else {
            // Guest → fallback sur le JSON du frontend
            $cartItems = json_decode($request->cart_data, true);
            if (empty($cartItems)) {
                return response()->json(['message' => 'Le panier est vide.'], 400);
            }
        }

        $createAccount = $request->boolean('create_account');

        // Validation création de compte
        if ($createAccount) {
            $request->validate([
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|min:8',
            ]);
        } elseif ($request->filled('email')) {
            $request->validate(['email' => 'nullable|email']);
        }

        DB::beginTransaction();

        $user         = null;
        $nomClient    = $request->nom;
        $prenomClient = $request->prenom;

        $roleClient = Role::where('name', 'Client')->firstOrFail();

        // ── CAS 1 : Utilisateur connecté ────────────────────
        if (Auth::check()) {
            $user         = Auth::user();
            $nomClient    = $user->nom;
            $prenomClient = $user->prenom;
            $user->update([
                'telephone' => $request->telephone,
                'adresse'   => $request->adresse,
            ]);

        // ── CAS 2 & 3 : Guest ───────────────────────────────
        } else {
            $emailToCheck = $request->filled('email')
                ? $request->email
                : $this->generateTemporaryEmail($request->telephone);

            $existingUser = User::where('email', $emailToCheck)->first();

            if ($existingUser) {
                $user = $existingUser;
                $user->update([
                    'nom'       => $request->nom,
                    'prenom'    => $request->prenom,
                    'telephone' => $request->telephone,
                    'adresse'   => $request->adresse,
                ]);
                if ($createAccount && $request->password) {
                    $user->update(['password' => Hash::make($request->password)]);
                }
            } else {
                $userData = [
                    'nom'       => $request->nom,
                    'prenom'    => $request->prenom,
                    'email'     => $emailToCheck,
                    'telephone' => $request->telephone,
                    'adresse'   => $request->adresse,
                    'role_id'   => $roleClient->id,
                    'password'  => Hash::make($createAccount && $request->password
                        ? $request->password
                        : Str::random(20)),
                ];
                $user = User::create($userData);
            }
        }

        // ── Insérer le panier en BDD pour les guests ─────────
        if (!Auth::check()) {
            foreach ($cartItems as $item) {
                Panier::create([
                    'user_id'    => $user->id,
                    'produit_id' => $item['id'],
                    'quantite'   => $item['quantity'],
                    'prixPanier' => $item['price'],
                    'statut'     => 'commande',
                ]);
            }
        }

        // ── Calcul du total ──────────────────────────────────
        $fraisLivraison = intval($request->shipping_cost ?? 0);
        $sousTotal      = collect($cartItems)->sum(fn($i) => $i['price'] * $i['quantity']);
        $total          = $sousTotal + $fraisLivraison;

        // ── Adresse complète ─────────────────────────────────
        $adresseComplete = $request->adresse;
        if ($request->pays !== 'Senegal' && $request->filled('ville')) {
            $adresseComplete .= ', ' . $request->code_postal . ' ' . $request->ville . ', ' . $request->region;
        }

        // ── Zone livraison ───────────────────────────────────
        $zoneLivraison = '';
        if ($request->pays === 'Senegal' && $request->zone_livraison) {
            $parts          = explode('|', $request->zone_livraison);
            $zoneLivraison  = $request->zone_livraison;
            $fraisLivraison = isset($parts[2]) ? intval($parts[2]) : $fraisLivraison;
        }

        // ── Formater les produits pour stockage ──────────────
        $produitsFormates = [];
        foreach ($cartItems as $item) {
            $type = 'gamme';
            if (isset($item['category']) && $item['category'] === 'Sport') {
                $type = 'sport';
            } elseif (isset($item['type']) && $item['type'] === 'sport') {
                $type = 'sport';
            }

            $produitsFormates[] = [
                'id'            => $item['id'],
                'nom'           => $item['name'] ?? $item['nom'],
                'quantite'      => $item['quantity'],
                'prix_unitaire' => $item['price'],
                'total'         => $item['price'] * $item['quantity'],
                'type'          => $type,
                'categorie'     => $item['category'] ?? 'Bio',
                'image'         => $item['image'] ?? null,
            ];
        }
        $produitsJson = json_encode($produitsFormates);

        // ── Créer la commande ────────────────────────────────
        $commande = Commande::create([
            'numeroCommande' => 'BIOSEN-' . str_pad(Commande::max('id') + 1, 3, '0', STR_PAD_LEFT),
            'montantTotal'     => $total,
            'user_id'          => $user->id,
            'noteCommande'     => $request->notes,
            'statut'           => 'en_attente',
            'email'            => $request->email ?? $user->email,
            'nom_client'       => $nomClient,
            'prenom_client'    => $prenomClient,
            'telephone_client' => $request->telephone,
            'adresse_client'   => $adresseComplete,
            'pays'             => $request->pays,
            'ville_zone'       => $zoneLivraison ?: ($request->ville ?? ''),
            'code_postal'      => $request->code_postal,
            'region'           => $request->region,
            'methode_paiement' => $request->payment_method,
            'is_guest'         => !Auth::check(),
            'produits'         => $produitsJson,
        ]);

        // ── Créer la livraison ───────────────────────────────
        $livraison = Livraison::create([
            'zone'          => $zoneLivraison ?: ($request->ville ?? ''),
            'statut'        => 'en_attente',
            'telephone'     => $request->telephone,
            'frais'         => $fraisLivraison,
            'commande_id'   => $commande->id,
            'user_id'       => $user->id,
            'pays'          => $request->pays,
            'adresse'       => $adresseComplete,
            'nom_client'    => $nomClient,
            'prenom_client' => $prenomClient,
        ]);

        // ── Créer la facture ─────────────────────────────────
        $facture = Facture::create([
            'commande_id'     => $commande->id,
            'numero_facture'  => 'FAC-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'date_emission'   => now(),
            'date_echeance'   => now()->addDays(30),
            'statut_paiement' => 'en_attente',
            'metadonnees'     => [
                'produits'         => $produitsFormates,
                'sous_total'       => $sousTotal,
                'frais_livraison'  => $fraisLivraison,
                'total'            => $total,
                'methode_paiement' => $request->payment_method,
                'nom_client'       => $nomClient . ' ' . $prenomClient,
                'telephone_client' => $request->telephone,
                'adresse_client'   => $adresseComplete,
                'pays'             => $request->pays,
            ],
        ]);

        // ── Vider le panier ──────────────────────────────────
        if (Auth::check()) {
            // Connecté → mettre les quantités à 0 et statut à "commande"
            Panier::where('user_id', Auth::id())
                ->where('statut', 'actif')
                ->update([
                    'quantite' => 0,
                    'statut'   => 'commande',
                ]);
        } else {
            // Guest → vider le panier en session
            session()->forget('cart');
        }

        // ── Stocker la commande guest en session ─────────────
        if (!Auth::check()) {
            $guestOrders   = session('guest_orders', []);
            $guestOrders[] = $commande->numeroCommande;
            session(['guest_orders' => $guestOrders]);
        }

        // ── Token si nouveau compte ──────────────────────────
        $token = null;
        if ($createAccount && !Auth::check()) {
            $token = auth('api')->login($user);
        }

        DB::commit();

        Log::info('Commande créée', [
            'order_number'   => $commande->numeroCommande,
            'numero_facture' => $facture->numero_facture,
            'user_id'        => $user->id,
            'total'          => $total,
        ]);

        return response()->json([
            'message'          => 'Commande créée avec succès.',
            'order_number'     => $commande->numeroCommande,
            'commande_id'      => $commande->id,
            'facture_id'       => $facture->id,
            'numero_facture'   => $facture->numero_facture,
            'token'            => $token,
            'whatsapp_message' => $this->generateWhatsAppMessage($commande, $livraison, $cartItems),
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        return response()->json(['errors' => $e->errors()], 422);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Checkout error: ' . $e->getMessage());
        return response()->json(['message' => $e->getMessage()], 500);
    }
}

    // ══════════════════════════════════════════════════════════
    // CONFIRMATION
    // ══════════════════════════════════════════════════════════
    public function confirmation($orderNumber)
    {
        $commande = Commande::where('numeroCommande', $orderNumber)
            ->with(['user', 'livraison'])
            ->first();

        if (!$commande) {
            return response()->json(['message' => 'Commande non trouvée.'], 404);
        }

        if (Auth::check() && Auth::id() !== $commande->user_id && !Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        // Décoder les produits correctement
        $produits = is_string($commande->produits)
            ? json_decode($commande->produits, true)
            : $commande->produits;

        // Formater les produits pour l'affichage
        $produitsFormates = [];
        if (!empty($produits)) {
            foreach ($produits as $produit) {
                // Nettoyer le chemin de l'image
                $image = $produit['image'] ?? '';
                if ($image && !str_starts_with($image, 'http')) {
                    $image = asset($image);
                }

                $produitsFormates[] = [
                    'id' => $produit['id'],
                    'nom' => $produit['nom'],
                    'quantite' => $produit['quantite'],
                    'prix_unitaire' => (float) $produit['prix_unitaire'],
                    'total' => (float) $produit['total'],
                    'type' => $produit['type'],
                    'categorie' => $produit['categorie'],
                    'image' => $image
                ];
            }
        }

        return response()->json([
            'commande'  => $commande,
            'livraison' => $commande->livraison,
            'user'      => $commande->user,
            'produits'  => $produitsFormates,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // STATUT COMMANDE
    // ══════════════════════════════════════════════════════════
    public function checkOrderStatus($orderNumber)
    {
        $commande = Commande::where('numeroCommande', $orderNumber)
            ->with(['livraison'])->first();

        if (!$commande) {
            return response()->json(['message' => 'Commande non trouvée.'], 404);
        }

        return response()->json([
            'order_number'    => $commande->numeroCommande,
            'statut'          => $commande->statut,
            'created_at'      => $commande->created_at->format('d/m/Y H:i'),
            'total'           => number_format($commande->montantTotal, 0, ',', ' ') . ' FCFA',
            'delivery_statut' => $commande->livraison?->statut ?? 'Non disponible',
            'delivery_address'=> $commande->livraison?->adresse ?? 'Non disponible',
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // MESSAGE WHATSAPP (retourné dans la réponse JSON)
    // ══════════════════════════════════════════════════════════
    public function getWhatsAppMessage($orderNumber)
    {
        $commande = Commande::where('numeroCommande', $orderNumber)
            ->with(['livraison'])->firstOrFail();

        // Le frontend devra passer les cartItems
        // car on ne les stocke plus en session
        return response()->json([
            'telephone' => $commande->livraison?->telephone,
            'message'   => $this->generateWhatsAppMessage($commande, $commande->livraison, []),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════
    private function generateTemporaryEmail(string $telephone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $telephone);
        return 'client_' . $clean . '@biosen100.com';
    }

    private function generateWhatsAppMessage($commande, $livraison, array $cartItems): string
    {
        $subtotal        = 0;
        $detailsProduits = '';

        foreach ($cartItems as $i => $item) {
            $itemTotal        = $item['price'] * $item['quantity'];
            $subtotal        += $itemTotal;
            $detailsProduits .= ($i + 1) . ". {$item['name']} (x{$item['quantity']}) : "
                . number_format($itemTotal, 0, ',', ' ') . " FCFA\n";
        }

        $zoneLivraison = '';
        if ($livraison && $livraison->pays === 'Senegal' && $livraison->zone) {
            $parts = explode('|', $livraison->zone);
            if (isset($parts[1])) $zoneLivraison = "\n📍 Zone : " . $parts[1];
        }

        $message = "🌿 *BIOSEN 100 - FACTURE DE COMMANDE* 🌿\n\n"
            . "📋 *INFORMATIONS COMMANDE*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "🎫 N° Commande : *#{$commande->numeroCommande}*\n"
            . "📅 Date : " . $commande->created_at->format('d/m/Y à H:i') . "\n\n"
            . "👤 *INFORMATIONS CLIENT*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "👨‍💼 Client : *{$livraison?->prenom_client} {$livraison?->nom_client}*\n"
            . "📞 Téléphone : *{$livraison?->telephone}*\n"
            . "📍 Adresse : {$livraison?->adresse}\n"
            . "🏙️ Pays : {$livraison?->pays}{$zoneLivraison}\n\n";

        if (!empty($detailsProduits)) {
            $message .= "🛒 *PRODUITS COMMANDÉS*\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                . $detailsProduits . "\n";
        }

        $message .= "💰 *DÉTAILS DE PAIEMENT*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "📦 Sous-total : " . number_format($commande->montantTotal - ($livraison?->frais ?? 0), 0, ',', ' ') . " FCFA\n"
            . "🚚 Frais livraison : " . number_format($livraison?->frais ?? 0, 0, ',', ' ') . " FCFA\n"
            . "💵 *TOTAL : " . number_format($commande->montantTotal, 0, ',', ' ') . " FCFA*\n\n"
            . "🙏 *MERCI POUR VOTRE CONFIANCE !*\n"
            . "_L'équipe BioSen 100_ 🌿";

        return $message;
    }

    /**
     * GÉNÉRER ET TÉLÉCHARGER LA FACTURE PDF (PUBLIC - SANS VÉRIFICATION)
     */
    public function generatePDF($orderNumber)
    {
        try {
            Log::info('Tentative de génération PDF pour commande: ' . $orderNumber);

            // Récupérer la commande avec les relations
            $commande = Commande::where('numeroCommande', $orderNumber)
                ->with(['livraison'])
                ->first();

            if (!$commande) {
                Log::error('Commande non trouvée: ' . $orderNumber);
                return response()->json(['error' => 'Commande non trouvée'], 404);
            }

            Log::info('Commande trouvée', ['id' => $commande->id, 'user_id' => $commande->user_id]);

            // Récupérer les produits depuis la commande
            $produits = [];

            if (!empty($commande->produits)) {
                if (is_string($commande->produits)) {
                    $produits = json_decode($commande->produits, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('Erreur JSON decode: ' . json_last_error_msg());
                        $produits = [];
                    }
                } else {
                    $produits = $commande->produits;
                }
            }

            Log::info('Produits décodés', ['count' => count($produits)]);

            // Si pas de produits, utiliser un tableau vide
            if (empty($produits)) {
                $produits = [];
            }

            // Formater les produits pour la vue
            $produitsFormates = [];
            foreach ($produits as $index => $produit) {
                // Vérifier que toutes les clés nécessaires existent
                $produitsFormates[] = [
                    'nom'          => $produit['nom'] ?? 'Produit sans nom',
                    'quantite'     => $produit['quantite'] ?? 1,
                    'prix_unitaire'=> $produit['prix_unitaire'] ?? 0,
                    'total'        => $produit['total'] ?? 0,
                ];
            }

            Log::info('Produits formatés', ['count' => count($produitsFormates)]);

            // Vérifier que la vue existe
            $viewPath = resource_path('views/pdf/invoice.blade.php');
            if (!file_exists($viewPath)) {
                Log::info('Vue PDF non trouvée, création...');
                // Créer le dossier si nécessaire
                if (!is_dir(dirname($viewPath))) {
                    mkdir(dirname($viewPath), 0755, true);
                }

                // Vue par défaut améliorée
                $viewContent = $this->getDefaultInvoiceView();
                file_put_contents($viewPath, $viewContent);
                Log::info('Vue PDF créée');
            }

            // Vérifier que DomPDF est installé
            if (!class_exists('Barryvdh\DomPDF\Facade\Pdf')) {
                Log::error('DomPDF non installé');
                return response()->json(['error' => 'Bibliothèque PDF non disponible'], 500);
            }

            // Générer le PDF avec les produits de la commande
            Log::info('Tentative de génération PDF avec loadView');

            $pdf = Pdf::loadView('pdf.invoice', [
                'commande' => $commande,
                'livraison' => $commande->livraison,
                'produits' => $produitsFormates,
                'orderNumber' => $orderNumber,
                'date' => now()->format('d/m/Y H:i')
            ]);

            Log::info('PDF généré avec succès');

            return $pdf->download('Facture_' . $orderNumber . '.pdf');

        } catch (\Exception $e) {
            Log::error('ERREUR PDF DÉTAILLÉE: ' . $e->getMessage());
            Log::error('Fichier: ' . $e->getFile() . ' Ligne: ' . $e->getLine());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'error' => 'Erreur lors de la génération du PDF',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    /**
     * Vue PDF par défaut améliorée
     */
    private function getDefaultInvoiceView()
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Facture {{ $orderNumber }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            color: #333;
        }
        .header {
            text-align: center;
            color: #287747;
            margin-bottom: 30px;
            border-bottom: 2px solid #287747;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 32px;
            margin-bottom: 5px;
        }
        .header h3 {
            font-size: 20px;
            color: #666;
            margin-top: 0;
        }
        .info-section {
            margin-bottom: 30px;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
        }
        .info-section h4 {
            color: #287747;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background: #287747;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .total-section {
            text-align: right;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #287747;
        }
        .total-line {
            font-size: 16px;
            margin: 5px 0;
        }
        .grand-total {
            font-size: 20px;
            font-weight: bold;
            color: #287747;
            margin-top: 10px;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>BioSen 100</h1>
        <h3>Facture N° {{ $orderNumber }}</h3>
        <p>Date: {{ $date }}</p>
    </div>

    <div class="info-section">
        <h4>Informations client</h4>
        <div class="info-grid">
            <div>
                <p><strong>Nom :</strong> {{ $commande->prenom_client ?? "Non renseigné" }} {{ $commande->nom_client ?? "" }}</p>
                <p><strong>Téléphone :</strong> {{ $commande->telephone_client ?? "Non renseigné" }}</p>
                <p><strong>Email :</strong> {{ $commande->email ?? "Non fourni" }}</p>
            </div>
            <div>
                <p><strong>Adresse :</strong> {{ $commande->adresse_client ?? "Non renseignée" }}</p>
                <p><strong>Pays :</strong> {{ $commande->pays ?? "Non renseigné" }}</p>
                <p><strong>Zone :</strong> {{ $commande->ville_zone ?? "Non spécifiée" }}</p>
            </div>
        </div>
    </div>

    <h4 style="color: #287747;">Produits commandés</h4>
    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th>Quantité</th>
                <th>Prix unitaire</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($produits as $produit)
            <tr>
                <td>{{ $produit["name"] ?? "Produit" }}</td>
                <td>{{ $produit["quantity"] ?? 0 }}</td>
                <td>{{ number_format($produit["price"] ?? 0, 0, ",", " ") }} FCFA</td>
                <td>{{ number_format($produit["total"] ?? 0, 0, ",", " ") }} FCFA</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align: center;">Aucun produit trouvé</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="total-section">
        @php
            $sousTotal = ($commande->montantTotal ?? 0) - (($livraison->frais ?? 0));
        @endphp
        <div class="total-line">
            <strong>Sous-total :</strong>
            {{ number_format($sousTotal, 0, ",", " ") }} FCFA
        </div>
        <div class="total-line">
            <strong>Frais de livraison :</strong>
            {{ number_format($livraison->frais ?? 0, 0, ",", " ") }} FCFA
        </div>
        <div class="grand-total">
            TOTAL : {{ number_format($commande->montantTotal ?? 0, 0, ",", " ") }} FCFA
        </div>
    </div>

    <div class="footer">
        <p>Merci pour votre confiance !</p>
        <p>L\'équipe BioSen 100</p>
        <p>www.biosen100.com</p>
    </div>
</body>
</html>';
    }

    // CheckoutController.php - Ajoute cette méthode

    public function initCheckout(Request $request)
    {
        try {
            // Valider les données (comme avant)
            $request->validate([
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'telephone' => 'required|string|max:20',
                'pays' => 'required|string|max:100',
                'adresse' => 'required|string|max:500',
                'cart_data' => 'required|string',
            ]);

            // Validation selon le pays
            if ($request->pays === 'Senegal') {
                $request->validate(['zone_livraison' => 'required|string']);
            } else {
                $request->validate([
                    'code_postal' => 'required|string|max:20',
                    'ville' => 'required|string|max:255',
                    'region' => 'required|string|max:255',
                ]);
            }

            $cartItems = json_decode($request->cart_data, true);
            $fraisLivraison = intval($request->shipping_cost ?? 0);
            $total = collect($cartItems)->sum(fn($i) => $i['price'] * $i['quantity']) + $fraisLivraison;

            // Générer un token temporaire
            $paymentToken = Str::random(32);

            // Stocker les données temporairement (dans la session ou cache)
            // Utilisons la session pour plus de simplicité
            session(['payment_' . $paymentToken => [
                'data' => $request->all(),
                'cart' => $cartItems,
                'total' => $total,
                'frais_livraison' => $fraisLivraison,
                'expires_at' => now()->addHours(1)->timestamp
            ]]);

            // Initialiser le paiement PayDunya
            $paydunyaResponse = $this->preparePaydunyaPayment([
                'total' => $total,
                'items' => $cartItems,
                'frais_livraison' => $fraisLivraison,
                'client' => [
                    'nom' => $request->nom,
                    'prenom' => $request->prenom,
                    'email' => $request->email ?? '',
                    'telephone' => $request->telephone,
                    'adresse' => $request->adresse
                ],
                'payment_token' => $paymentToken
            ]);

            return response()->json($paydunyaResponse);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Erreur init checkout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du paiement'
            ], 500);
        }
    }

    // CheckoutController.php - REMPLACE ces méthodes

    private function configurePaydunya()
    {
        $mode = config('paydunya.mode', 'test');
        $config = config("paydunya.{$mode}");

        try {
            // Vérifier quelle classe utiliser
            if (class_exists('\Paydunya\Setup')) {
                // Nouvelle version du SDK
                \Paydunya\Setup::setMasterKey($config['master_key'] ?? '');
                \Paydunya\Setup::setPublicKey($config['public_key'] ?? '');
                \Paydunya\Setup::setPrivateKey($config['private_key'] ?? '');
                \Paydunya\Setup::setToken($config['token'] ?? '');
                \Paydunya\Setup::setMode($mode == 'live' ? 'live' : 'test');
                Log::info('✅ PayDunya configuré avec Setup');
            } else {
                // Ancienne version du SDK
                \Paydunya\Paydunya::setMasterKey($config['master_key'] ?? '');
                \Paydunya\Paydunya::setPublicKey($config['public_key'] ?? '');
                \Paydunya\Paydunya::setPrivateKey($config['private_key'] ?? '');
                \Paydunya\Paydunya::setMode($mode == 'live' ? 'live' : 'test');
                Log::info('✅ PayDunya configuré avec Paydunya');
            }
        } catch (\Exception $e) {
            Log::error('❌ Erreur configuration PayDunya: ' . $e->getMessage());
            throw $e;
        }
    }

    private function configureStore()
    {
        $store = config('paydunya.store');

        try {
            // Vérifier quelle classe Store utiliser
            if (class_exists('\Paydunya\Checkout\Store')) {
                \Paydunya\Checkout\Store::setName($store['name']);
                \Paydunya\Checkout\Store::setTagline($store['tagline']);
                \Paydunya\Checkout\Store::setPhoneNumber($store['phone_number']);
                \Paydunya\Checkout\Store::setPostalAddress($store['address']);
                \Paydunya\Checkout\Store::setWebsiteUrl($store['website_url']);
                \Paydunya\Checkout\Store::setLogoUrl($store['logo_url']);
                Log::info('✅ Store configuré avec Checkout\Store');
            } else {
                // Ancienne version
                \Paydunya\Checkout\Store::setName($store['name']);
                \Paydunya\Checkout\Store::setTagline($store['tagline']);
                \Paydunya\Checkout\Store::setPhoneNumber($store['phone_number']);
                \Paydunya\Checkout\Store::setPostalAddress($store['address']);
                \Paydunya\Checkout\Store::setWebsiteUrl($store['website_url']);
                \Paydunya\Checkout\Store::setLogoUrl($store['logo_url']);
            }
        } catch (\Exception $e) {
            Log::error('❌ Erreur configuration Store: ' . $e->getMessage());
        }
    }

    private function preparePaydunyaPayment($data)
    {
        try {
            // Configuration
            $this->configurePaydunya();
            $this->configureStore();

            // Création de la facture - Vérifier la classe disponible
            if (class_exists('\Paydunya\Checkout\CheckoutInvoice')) {
                $invoice = new \Paydunya\Checkout\CheckoutInvoice();
            } else {
                $invoice = new \Paydunya\Checkout\Invoice();
            }

            // Ajout des produits
            foreach ($data['items'] as $item) {
                $invoice->addItem(
                    $item['name'] ?? $item['nom'],
                    $item['quantity'],
                    $item['price'],
                    $item['price'] * $item['quantity']
                );
            }

            // Frais de livraison
            if ($data['frais_livraison'] > 0) {
                $invoice->addItem(
                    'Frais de livraison',
                    1,
                    $data['frais_livraison'],
                    $data['frais_livraison']
                );
            }

            $invoice->setTotalAmount($data['total']);
            $invoice->setDescription("Paiement commande BioSen100");

            // ✅ NE PAS UTILISER setCustomerInfo - utiliser addCustomData à la place
            $invoice->addCustomData('client_nom', $data['client']['prenom'] . ' ' . $data['client']['nom']);
            $invoice->addCustomData('client_telephone', $data['client']['telephone']);
            $invoice->addCustomData('client_email', $data['client']['email']);
            $invoice->addCustomData('client_adresse', $data['client']['adresse']);
            $invoice->addCustomData('payment_token', $data['payment_token']);

            // URLs de retour
            $frontendUrl = config('paydunya.store.website_url', 'http://localhost:4200');
            $invoice->setCancelUrl($frontendUrl . "/checkout/cancel");
            $invoice->setReturnUrl($frontendUrl . "/checkout/success?token={$data['payment_token']}");

            // Création de la facture
            if ($invoice->create()) {
                Log::info('✅ Facture PayDunya créée', ['token' => $invoice->token]);
                return [
                    'success' => true,
                    'payment_url' => $invoice->getInvoiceUrl(),
                    'token' => $invoice->token,
                    'payment_token' => $data['payment_token']
                ];
            } else {
                $errorMsg = $invoice->response_text ?? 'Erreur inconnue';
                Log::error('❌ Erreur PayDunya: ' . $errorMsg);
                return [
                    'success' => false,
                    'message' => 'Erreur paiement: ' . $errorMsg
                ];
            }
        } catch (\Exception $e) {
            Log::error('❌ Exception PayDunya: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur de paiement: ' . $e->getMessage()
            ];
        }
    }

    public function confirmPaymentAndCreateOrder(Request $request)
    {
        try {
            $paymentToken = $request->input('payment_token');

            // Récupérer les données temporaires de la session
            $pendingData = session('payment_' . $paymentToken);

            if (!$pendingData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session de paiement expirée ou invalide'
                ], 400);
            }

            // Vérifier que le token n'a pas expiré
            if ($pendingData['expires_at'] < now()->timestamp) {
                session()->forget('payment_' . $paymentToken);
                return response()->json([
                    'success' => false,
                    'message' => 'La session de paiement a expiré'
                ], 400);
            }

            DB::beginTransaction();

            $data = $pendingData['data'];
            $cartItems = $pendingData['cart'];
            $total = $pendingData['total'];
            $fraisLivraison = $pendingData['frais_livraison'];

            // Gestion utilisateur (connecté ou guest)
            $user = null;
            $createAccount = filter_var($data['create_account'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $emailToCheck = $data['email'] ?? $this->generateTemporaryEmail($data['telephone']);

            if (Auth::check()) {
                $user = Auth::user();
            } else {
                $existingUser = User::where('email', $emailToCheck)->first();

                if ($existingUser) {
                    $user = $existingUser;
                } else {
                    $roleClient = Role::where('name', 'Client')->firstOrFail();
                    $userData = [
                        'nom' => $data['nom'],
                        'prenom' => $data['prenom'],
                        'email' => $emailToCheck,
                        'telephone' => $data['telephone'],
                        'adresse' => $data['adresse'],
                        'role_id' => $roleClient->id,
                        'password' => Hash::make($createAccount && !empty($data['password'])
                            ? $data['password']
                            : Str::random(20)),
                    ];
                    $user = User::create($userData);
                }
            }

            // Adresse complète
            $adresseComplete = $data['adresse'];
            if ($data['pays'] !== 'Senegal' && !empty($data['ville'])) {
                $adresseComplete .= ', ' . $data['code_postal'] . ' ' . $data['ville'] . ', ' . $data['region'];
            }

            // Zone livraison
            $zoneLivraison = $data['zone_livraison'] ?? '';

            // Formater les produits
            $produitsFormates = [];
            foreach ($cartItems as $item) {
                $type = 'gamme';
                if (isset($item['category']) && $item['category'] === 'Sport') {
                    $type = 'sport';
                }

                $produitsFormates[] = [
                    'id' => $item['id'],
                    'nom' => $item['name'] ?? $item['nom'],
                    'quantite' => $item['quantity'],
                    'prix_unitaire' => $item['price'],
                    'total' => $item['price'] * $item['quantity'],
                    'type' => $type,
                    'categorie' => $item['category'] ?? 'Bio',
                    'image' => $item['image'] ?? null,
                ];
            }

            // Créer la commande
            $commande = Commande::create([
                'numeroCommande' => 'BIOSEN-' . time() . '-' . strtoupper(Str::random(4)),
                'montantTotal' => $total,
                'user_id' => $user->id,
                'noteCommande' => $data['notes'] ?? null,
                'statut' => 'payee', // 👈 Statut directement "payée"
                'email' => $data['email'] ?? $user->email,
                'nom_client' => $data['nom'],
                'prenom_client' => $data['prenom'],
                'telephone_client' => $data['telephone'],
                'adresse_client' => $adresseComplete,
                'pays' => $data['pays'],
                'ville_zone' => $zoneLivraison,
                'code_postal' => $data['code_postal'] ?? null,
                'region' => $data['region'] ?? null,
                'methode_paiement' => 'paydunya',
                'is_guest' => !Auth::check(),
                'produits' => json_encode($produitsFormates),
            ]);

            // Créer la livraison
            $livraison = Livraison::create([
                'zone' => $zoneLivraison,
                'statut' => 'en_attente',
                'telephone' => $data['telephone'],
                'frais' => $fraisLivraison,
                'commande_id' => $commande->id,
                'user_id' => $user->id,
                'pays' => $data['pays'],
                'adresse' => $adresseComplete,
                'nom_client' => $data['nom'],
                'prenom_client' => $data['prenom'],
            ]);

            // Nettoyer la session
            session()->forget('payment_' . $paymentToken);

            DB::commit();

            Log::info('Commande créée après paiement', [
                'order_number' => $commande->numeroCommande,
                'user_id' => $user->id,
                'total' => $total,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'order_number' => $commande->numeroCommande,
                'commande_id' => $commande->id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création commande après paiement: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande'
            ], 500);
        }
    }
}
