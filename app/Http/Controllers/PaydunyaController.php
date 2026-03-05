<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Paydunya\Paydunya;
use Paydunya\Checkout\CheckoutInvoice;
use Paydunya\Checkout\Store;

class PaydunyaController extends Controller
{
    protected $invoice;

    public function __construct()
    {
        $this->configurePaydunya();
        $this->configureStore();
        $this->invoice = new CheckoutInvoice();
    }

    private function configurePaydunya()
    {
        $mode = config('paydunya.mode', 'test');
        $config = config("paydunya.{$mode}");

        Paydunya::setMasterKey($config['master_key'] ?? '');
        Paydunya::setPublicKey($config['public_key'] ?? '');
        Paydunya::setPrivateKey($config['private_key'] ?? '');
        Paydunya::setMode($mode == 'live' ? 'live' : 'test');
    }

    private function configureStore()
    {
        $store = config('paydunya.store');

        Store::setName($store['name']);
        Store::setTagline($store['tagline']);
        Store::setPhoneNumber($store['phone_number']);
        Store::setPostalAddress($store['address']);
        Store::setWebsiteUrl($store['website_url']);
        Store::setLogoUrl($store['logo_url']);
    }

    public function initPayment($commandeId)
    {
        try {
            Log::info('Initialisation paiement PayDunya pour commande: ' . $commandeId);

            $commande = Commande::with('livraison')->findOrFail($commandeId);

            // Ajouter les produits
            $produits = json_decode($commande->produits, true);
            foreach ($produits as $item) {
                $this->invoice->addItem(
                    $item['nom'],
                    $item['quantite'],
                    $item['prix_unitaire'],
                    $item['total']
                );
            }

            // Frais de livraison
            if ($commande->livraison && $commande->livraison->frais > 0) {
                $this->invoice->addItem(
                    'Frais de livraison',
                    1,
                    $commande->livraison->frais,
                    $commande->livraison->frais
                );
            }

            $this->invoice->setTotalAmount($commande->montantTotal);
            $this->invoice->setDescription("Paiement commande N° {$commande->numeroCommande}");

            // Infos client
            $this->invoice->setCustomerInfo(
                $commande->prenom_client . ' ' . $commande->nom_client,
                $commande->telephone_client,
                $commande->email,
                $commande->adresse_client
            );

            // Données internes
            $this->invoice->setInternalData([
                'commande_id' => $commande->id,
                'numero_commande' => $commande->numeroCommande
            ]);

            // URLs de retour
            $frontendUrl = config('paydunya.store.website_url');
            $this->invoice->setCancelUrl($frontendUrl . "/checkout/cancel?commande={$commande->numeroCommande}");
            $this->invoice->setReturnUrl($frontendUrl . "/checkout/success?commande={$commande->numeroCommande}");

            if ($this->invoice->create()) {
                return response()->json([
                    'success' => true,
                    'payment_url' => $this->invoice->getInvoiceUrl(),
                    'token' => $this->invoice->token
                ]);
            } else {
                Log::error('Erreur PayDunya: ' . $this->invoice->response_text);
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur paiement: ' . $this->invoice->response_text
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Exception PayDunya: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }
}
