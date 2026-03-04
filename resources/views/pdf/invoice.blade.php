<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Facture {{ $orderNumber }}</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; color: #287747; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #287747; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        .total { text-align: right; font-size: 18px; font-weight: bold; margin-top: 20px; }
        .product-img { width: 50px; height: 50px; object-fit: cover; }
    </style>
</head>
<body>
<div class="header">
    <h1>BioSen 100</h1>
    <h3>Facture N° {{ $orderNumber }}</h3>
    <p>Date: {{ $date }}</p>
</div>

<div class="client-info">
    <p><strong>Client:</strong> {{ $commande->prenom_client }} {{ $commande->nom_client }}</p>
    <p><strong>Téléphone:</strong> {{ $commande->telephone_client }}</p>
    <p><strong>Adresse:</strong> {{ $commande->adresse_client }}</p>
    <p><strong>Pays:</strong> {{ $commande->pays }}</p>
</div>

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
    @foreach($produits as $produit)
        <tr>
            <td>
                {{ $produit['nom'] }}
            </td>
            <td>{{ $produit['quantite'] }}</td>
            <td>{{ number_format($produit['prix_unitaire'], 0, ',', ' ') }} FCFA</td>
            <td>{{ number_format($produit['total'], 0, ',', ' ') }} FCFA</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="total">
    <p>Sous-total: {{ number_format($commande->montantTotal - ($livraison->frais ?? 0), 0, ',', ' ') }} FCFA</p>
    <p>Frais de livraison: {{ number_format($livraison->frais ?? 0, 0, ',', ' ') }} FCFA</p>
    <p style="font-size: 20px;">TOTAL: {{ number_format($commande->montantTotal, 0, ',', ' ') }} FCFA</p>
</div>

<div style="margin-top: 50px; text-align: center; color: #666;">
    <p>Merci pour votre confiance !</p>
    <p>L'équipe BioSen 100</p>
</div>
</body>
</html>
