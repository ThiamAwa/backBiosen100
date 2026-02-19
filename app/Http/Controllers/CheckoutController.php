<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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

            // ── Token si nouveau compte ──────────────────────────
            $token = null;
            if ($createAccount && !Auth::check()) {
                $token = $user->createToken('api-token')->plainTextToken;
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

        return response()->json([
            'commande'  => $commande,
            'livraison' => $commande->livraison,
            'user'      => $commande->user,
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
}
