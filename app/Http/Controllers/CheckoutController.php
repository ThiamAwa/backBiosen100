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

            if ($request->pays === 'Senegal') {
                $request->validate(['zone_livraison' => 'required|string']);
            } else {
                $request->validate([
                    'code_postal' => 'required|string|max:20',
                    'ville'       => 'required|string|max:255',
                    'region'      => 'required|string|max:255',
                ]);
            }

            // ── Récupération du panier ──────────────────────────────
            if (Auth::check()) {
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
                // Guest → données viennent du frontend via cart_data JSON
                // On NE touche PAS à la table paniers pour les guests
                // car elle a une FK vers produits (Bio seulement)
                // et le panier peut contenir des produits sport.
                $cartItems = json_decode($request->cart_data, true);
                if (empty($cartItems)) {
                    return response()->json(['message' => 'Le panier est vide.'], 400);
                }
            }

            $createAccount = $request->boolean('create_account');

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
            $roleClient   = Role::where('name', 'Client')->firstOrFail();

            // ── CAS 1 : Utilisateur connecté ─────────────────────
            // ══════════════════════════════════════════════════════════
            // GESTION UTILISATEUR
            // ══════════════════════════════════════════════════════════
            if (Auth::check()) {
                // ── CAS 1 : Utilisateur connecté ─────────────────────
                $user = Auth::user();
                $nomClient = $user->nom;
                $prenomClient = $user->prenom;
                
                $user->update([
                    'telephone' => $request->telephone,
                    'adresse'   => $request->adresse,
                ]);
                
            } else {
                // ── CAS 2 : Guest (non connecté) ────────────────────
                $createAccount = $request->boolean('create_account');
                
                if ($createAccount && $request->filled('email') && $request->filled('password')) {
                    // ── SOUS-CAS 2A : Création de compte volontaire ──
                    $request->validate([
                        'email'    => 'required|email|unique:users,email',
                        'password' => 'required|min:8',
                    ]);
                    
                    $user = User::create([
                        'nom'       => $request->nom,
                        'prenom'    => $request->prenom,
                        'email'     => $request->email,
                        'telephone' => $request->telephone,
                        'adresse'   => $request->adresse,
                        'role_id'   => $roleClient->id,
                        'password'  => Hash::make($request->password),
                    ]);
                    
                    // Générer un token pour connexion automatique
                    $token = auth('api')->login($user);
                    
                } else {
                    // ── SOUS-CAS 2B : Simple commande sans compte ───
                    // AUCUN email, AUCUN mot de passe (tout est NULL)
                    $user = User::create([
                        'nom'       => $request->nom,
                        'prenom'    => $request->prenom,
                        'email'     => null,        
                        'telephone' => $request->telephone,
                        'adresse'   => $request->adresse,
                        'role_id'   => $roleClient->id,
                        'password'  => null,        
                    ]);
                }
            }
            // ── Calcul du total ───────────────────────────────────
            $fraisLivraison = intval($request->shipping_cost ?? 0);
            $sousTotal      = collect($cartItems)->sum(fn($i) => $i['price'] * $i['quantity']);
            $total          = $sousTotal + $fraisLivraison;

            // ── Adresse complète ──────────────────────────────────
            $adresseComplete = $request->adresse;
            if ($request->pays !== 'Senegal' && $request->filled('ville')) {
                $adresseComplete .= ', ' . $request->code_postal . ' ' . $request->ville . ', ' . $request->region;
            }

            // ── Zone livraison ────────────────────────────────────
            $zoneLivraison = '';
            if ($request->pays === 'Senegal' && $request->zone_livraison) {
                $parts          = explode('|', $request->zone_livraison);
                $zoneLivraison  = $request->zone_livraison;
                $fraisLivraison = isset($parts[2]) ? intval($parts[2]) : $fraisLivraison;
            }

            // ── Formater les produits ─────────────────────────────
            $produitsFormates = [];
            foreach ($cartItems as $item) {
                $type = 'gamme';
                if (isset($item['category']) && strtolower($item['category']) === 'sport') $type = 'sport';
                elseif (isset($item['type'])  && strtolower($item['type'])     === 'sport') $type = 'sport';

                $produitsFormates[] = [
                    'id'            => $item['id'],
                    'nom'           => $item['name'] ?? $item['nom'] ?? 'Produit',
                    'quantite'      => $item['quantity'],
                    'prix_unitaire' => $item['price'],
                    'total'         => $item['price'] * $item['quantity'],
                    'type'          => $type,
                    'categorie'     => $item['category'] ?? 'Bio',
                    'image'         => $item['image'] ?? null,
                ];
            }

            // ── Créer la commande ─────────────────────────────────
            $commande = Commande::create([
                'numeroCommande'   => 'BIOSEN-' . str_pad(Commande::max('id') + 1, 3, '0', STR_PAD_LEFT),
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
                'methode_paiement' => $request->payment_method ?? 'whatsapp',
                'is_guest'         => !Auth::check(),
                'produits'         => json_encode($produitsFormates),
            ]);

            // ── Créer la livraison ────────────────────────────────
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

            // ── Créer la facture ──────────────────────────────────
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
                    'methode_paiement' => $request->payment_method ?? 'whatsapp',
                    'nom_client'       => $nomClient . ' ' . $prenomClient,
                    'telephone_client' => $request->telephone,
                    'adresse_client'   => $adresseComplete,
                    'pays'             => $request->pays,
                ],
            ]);

            // ── Vider le panier (connecté seulement) ─────────────
            if (Auth::check()) {
                Panier::where('user_id', Auth::id())
                    ->where('statut', 'actif')
                    ->update(['quantite' => 0, 'statut' => 'commande']);
            }
            // Pour les guests : le panier frontend sera vidé
            // par cartService.clearCart() côté Angular après la réponse 201.

            // ── Session guest ─────────────────────────────────────
            if (!Auth::check()) {
                $guestOrders   = session('guest_orders', []);
                $guestOrders[] = $commande->numeroCommande;
                session(['guest_orders' => $guestOrders]);
            }

            // ── Token si nouveau compte ───────────────────────────
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

        $produits = is_string($commande->produits)
            ? json_decode($commande->produits, true)
            : $commande->produits;

        $produitsFormates = [];
        if (!empty($produits)) {
            foreach ($produits as $produit) {
                $image = $produit['image'] ?? '';
                if ($image && !str_starts_with($image, 'http')) {
                    $image = asset($image);
                }
                $produitsFormates[] = [
                    'id'            => $produit['id'],
                    'nom'           => $produit['nom'],
                    'quantite'      => $produit['quantite'],
                    'prix_unitaire' => (float) $produit['prix_unitaire'],
                    'total'         => (float) $produit['total'],
                    'type'          => $produit['type'],
                    'categorie'     => $produit['categorie'],
                    'image'         => $image,
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
            'order_number'     => $commande->numeroCommande,
            'statut'           => $commande->statut,
            'created_at'       => $commande->created_at->format('d/m/Y H:i'),
            'total'            => number_format($commande->montantTotal, 0, ',', ' ') . ' FCFA',
            'delivery_statut'  => $commande->livraison?->statut ?? 'Non disponible',
            'delivery_address' => $commande->livraison?->adresse ?? 'Non disponible',
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // MESSAGE WHATSAPP
    // ══════════════════════════════════════════════════════════
    public function getWhatsAppMessage($orderNumber)
    {
        $commande = Commande::where('numeroCommande', $orderNumber)
            ->with(['livraison'])->firstOrFail();

        return response()->json([
            'telephone' => $commande->livraison?->telephone,
            'message'   => $this->generateWhatsAppMessage($commande, $commande->livraison, []),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // GÉNÉRATION PDF
    // ══════════════════════════════════════════════════════════
    public function generatePDF($orderNumber)
    {
        try {
            $commande = Commande::where('numeroCommande', $orderNumber)
                ->with(['livraison'])->first();

            if (!$commande) {
                return response()->json(['error' => 'Commande non trouvée'], 404);
            }

            $produits = [];
            if (!empty($commande->produits)) {
                $produits = is_string($commande->produits)
                    ? json_decode($commande->produits, true)
                    : $commande->produits;
            }

            $produitsFormates = array_map(fn($p) => [
                'nom'           => $p['nom']          ?? 'Produit',
                'quantite'      => $p['quantite']      ?? 1,
                'prix_unitaire' => $p['prix_unitaire'] ?? 0,
                'total'         => $p['total']         ?? 0,
            ], $produits);

            $viewPath = resource_path('views/pdf/invoice.blade.php');
            if (!file_exists($viewPath)) {
                if (!is_dir(dirname($viewPath))) mkdir(dirname($viewPath), 0755, true);
                file_put_contents($viewPath, $this->getDefaultInvoiceView());
            }

            $pdf = Pdf::loadView('pdf.invoice', [
                'commande'    => $commande,
                'livraison'   => $commande->livraison,
                'produits'    => $produitsFormates,
                'orderNumber' => $orderNumber,
                'date'        => now()->format('d/m/Y H:i'),
            ]);

            return $pdf->download('Facture_' . $orderNumber . '.pdf');

        } catch (\Exception $e) {
            Log::error('ERREUR PDF: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur PDF', 'message' => $e->getMessage()], 500);
        }
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

    private function getDefaultInvoiceView(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Facture {{ $orderNumber }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        .header { text-align: center; color: #287747; margin-bottom: 30px; border-bottom: 2px solid #287747; padding-bottom: 20px; }
        .header h1 { font-size: 32px; margin-bottom: 5px; }
        .info-section { margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-radius: 10px; }
        .info-section h4 { color: #287747; margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #287747; color: white; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
        .total-section { text-align: right; margin-top: 30px; padding-top: 20px; border-top: 2px solid #287747; }
        .grand-total { font-size: 20px; font-weight: bold; color: #287747; margin-top: 10px; }
        .footer { text-align: center; margin-top: 50px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BioSen 100</h1>
        <h3>Facture N° {{ $orderNumber }}</h3>
        <p>Date : {{ $date }}</p>
    </div>
    <div class="info-section">
        <h4>Informations client</h4>
        <p><strong>Nom :</strong> {{ $commande->prenom_client ?? "" }} {{ $commande->nom_client ?? "" }}</p>
        <p><strong>Téléphone :</strong> {{ $commande->telephone_client ?? "Non renseigné" }}</p>
        <p><strong>Adresse :</strong> {{ $commande->adresse_client ?? "Non renseignée" }}</p>
        <p><strong>Pays :</strong> {{ $commande->pays ?? "Non renseigné" }}</p>
    </div>
    <h4 style="color: #287747;">Produits commandés</h4>
    <table>
        <thead>
            <tr><th>Produit</th><th>Quantité</th><th>Prix unitaire</th><th>Total</th></tr>
        </thead>
        <tbody>
            @forelse($produits as $produit)
            <tr>
                <td>{{ $produit["nom"] ?? "Produit" }}</td>
                <td>{{ $produit["quantite"] ?? 0 }}</td>
                <td>{{ number_format($produit["prix_unitaire"] ?? 0, 0, ",", " ") }} FCFA</td>
                <td>{{ number_format($produit["total"] ?? 0, 0, ",", " ") }} FCFA</td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;">Aucun produit</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="total-section">
        @php $sousTotal = ($commande->montantTotal ?? 0) - ($livraison->frais ?? 0); @endphp
        <p><strong>Sous-total :</strong> {{ number_format($sousTotal, 0, ",", " ") }} FCFA</p>
        <p><strong>Frais de livraison :</strong> {{ number_format($livraison->frais ?? 0, 0, ",", " ") }} FCFA</p>
        <div class="grand-total">TOTAL : {{ number_format($commande->montantTotal ?? 0, 0, ",", " ") }} FCFA</div>
    </div>
    <div class="footer">
        <p>Merci pour votre confiance !</p>
        <p>L\'équipe BioSen 100 🌿</p>
    </div>
</body>
</html>';
    }
}
