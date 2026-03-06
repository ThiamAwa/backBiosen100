<?php
namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Livraison;
use App\Models\Panier;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Tymon\JWTAuth\Facades\JWTAuth;

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
                'cart_data'      => 'required|string',
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

            $cartItems = json_decode($request->cart_data, true);
            if (empty($cartItems)) {
                return response()->json(['message' => 'Le panier est vide.'], 400);
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
                $user = Auth::user();
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

            // ── Calcul du total ──────────────────────────────────
            $fraisLivraison = intval($request->shipping_cost ?? 0);
            $total = collect($cartItems)->sum(fn($i) => $i['price'] * $i['quantity']);
            $total += $fraisLivraison;

            // ── Adresse complète ─────────────────────────────────
            $adresseComplete = $request->adresse;
            if ($request->pays !== 'Senegal' && $request->filled('ville')) {
                $adresseComplete .= ', ' . $request->code_postal . ' ' . $request->ville . ', ' . $request->region;
            }

            // ── Zone livraison ───────────────────────────────────
            $zoneLivraison = '';
            if ($request->pays === 'Senegal' && $request->zone_livraison) {
                $parts = explode('|', $request->zone_livraison);
                $zoneLivraison  = $request->zone_livraison;
                $fraisLivraison = isset($parts[2]) ? intval($parts[2]) : $fraisLivraison;
            }
            // ── Formater les produits pour stockage ───────────────────
            $produitsFormates = [];

            foreach ($cartItems as $item) {
                // Déterminer le type (sport ou gamme)
                $type = 'gamme'; // par défaut
                if (isset($item['category']) && $item['category'] === 'Sport') {
                    $type = 'sport';
                } elseif (isset($item['type']) && $item['type'] === 'sport') {
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
            $produitsJson = json_encode($produitsFormates);
            // ── Créer la commande ────────────────────────────────
            $commande = Commande::create([
                'numeroCommande'   => 'BIOSEN-' . time() . '-' . strtoupper(Str::random(4)),
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

            // ── Vider panier si connecté ─────────────────────────
            if (Auth::check()) {
                Panier::where('user_id', Auth::id())->delete();
            }
            // ── Après la création de la commande, stocker dans la session pour les invités ──
            if (!Auth::check()) {
                // Récupérer la liste des commandes invitées
                $guestOrders = session('guest_orders', []);
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
                'order_number' => $commande->numeroCommande,
                'user_id'      => $user->id,
                'total'        => $total,
            ]);

            return response()->json([
                'message'      => 'Commande créée avec succès.',
                'order_number' => $commande->numeroCommande,
                'commande_id'  => $commande->id,
                'token'        => $token, // null si déjà connecté
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
}
