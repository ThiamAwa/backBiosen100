<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $orderNumber }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #2d2d2d;
            background: #fff;
            padding: 30px;
        }

        /* ── HEADER ─────────────────────────────────────────── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 28px;
            border-bottom: 3px solid #287747;
            padding-bottom: 18px;
        }
        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 50%;
        }
        .header-right {
            display: table-cell;
            vertical-align: middle;
            width: 50%;
            text-align: right;
        }
        .logo {
            width: 90px;
            margin-bottom: 6px;
        }
        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #287747;
            letter-spacing: 1px;
        }
        .company-sub {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
        }
        .invoice-title {
            font-size: 26px;
            font-weight: bold;
            color: #287747;
            letter-spacing: 2px;
        }
        .invoice-number {
            font-size: 16px;
            font-weight: bold;
            color: #2d2d2d;
            margin-top: 4px;
        }
        .invoice-date {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }

        /* ── INFO BAND ──────────────────────────────────────── */
        .info-band {
            display: table;
            width: 100%;
            margin-bottom: 24px;
            background: #f4f9f6;
            border-radius: 6px;
            padding: 16px 20px;
        }
        .info-band-left {
            display: table-cell;
            width: 40%;
            vertical-align: top;
        }
        .info-band-right {
            display: table-cell;
            width: 60%;
            vertical-align: top;
            text-align: right;
        }
        .info-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #287747;
            font-weight: bold;
            margin-bottom: 3px;
            letter-spacing: 0.8px;
        }
        .info-value {
            font-size: 13px;
            font-weight: bold;
            color: #2d2d2d;
            margin-bottom: 1px;
        }
        .info-sub {
            font-size: 10px;
            color: #555;
            margin-bottom: 2px;
        }

        /* ── REFS TABLE ─────────────────────────────────────── */
        .refs-table {
            display: table;
            margin-bottom: 24px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            width: 100%;
            overflow: hidden;
        }
        .refs-row {
            display: table-row;
        }
        .refs-cell-label {
            display: table-cell;
            width: 130px;
            background: #f4f9f6;
            color: #287747;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 7px 12px;
            letter-spacing: 0.7px;
            border-bottom: 1px solid #e8e8e8;
            vertical-align: middle;
        }
        .refs-cell-value {
            display: table-cell;
            padding: 7px 14px;
            font-size: 11px;
            font-weight: bold;
            color: #2d2d2d;
            border-bottom: 1px solid #e8e8e8;
            vertical-align: middle;
        }

        /* ── PRODUCTS TABLE ─────────────────────────────────── */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .products-table thead tr th {
            background: #287747;
            color: #fff;
            padding: 11px 14px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: left;
            font-weight: bold;
        }
        .products-table thead tr th.text-center { text-align: center; }
        .products-table thead tr th.text-right  { text-align: right; }

        .products-table tbody tr td {
            padding: 11px 14px;
            font-size: 12px;
            color: #2d2d2d;
            border-bottom: 1px solid #ececec;
            vertical-align: middle;
        }
        .products-table tbody tr td.text-center { text-align: center; }
        .products-table tbody tr td.text-right  { text-align: right; font-weight: bold; }

        .products-table tbody tr:nth-child(even) td {
            background: #f9fdfb;
        }
        .num-badge {
            display: inline-block;
            vertical-align: middle;
            text-align: center;
            line-height: 22px;
            width: 22px;
            height: 22px;
            background: #287747;
            color: #fff;
            border-radius: 20%;
            font-size: 10px;
            font-weight: bold;
        }

        /* ── TOTALS ─────────────────────────────────────────── */
        .totals-wrapper {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .totals-left {
            display: table-cell;
            width: 55%;
            vertical-align: bottom;
        }
        .totals-right {
            display: table-cell;
            width: 45%;
            vertical-align: top;
        }
        .amount-in-words {
            font-size: 9px;
            color: #777;
            font-style: italic;
            padding-top: 6px;
        }
        .totals-box {
            background: #f4f9f6;
            border-radius: 8px;
            overflow: hidden;
        }
        .totals-row {
            display: table;
            width: 100%;
            padding: 8px 16px;
            border-bottom: 1px solid #e0ece5;
        }
        .totals-row-label {
            display: table-cell;
            font-size: 11px;
            color: #555;
        }
        .totals-row-value {
            display: table-cell;
            font-size: 11px;
            font-weight: bold;
            color: #2d2d2d;
            text-align: right;
        }
        .totals-grand {
            background: #287747;
            display: table;
            width: 100%;
            padding: 14px 16px;
        }
        .totals-grand-label {
            display: table-cell;
            font-size: 12px;
            font-weight: bold;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .totals-grand-value {
            display: table-cell;
            font-size: 20px;
            font-weight: bold;
            color: #fff;
            text-align: right;
        }
        .totals-grand-currency {
            font-size: 11px;
            font-weight: normal;
            margin-left: 4px;
        }

        /* ── CONDITIONS ─────────────────────────────────────── */
        .conditions-section {
            border-top: 2px solid #287747;
            padding-top: 14px;
            margin-top: 10px;
        }
        .conditions-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #287747;
            margin-bottom: 5px;
            letter-spacing: 0.8px;
        }
        .conditions-text {
            font-size: 10px;
            color: #666;
            line-height: 1.5;
        }

        /* ── FOOTER ─────────────────────────────────────────── */
        .footer {
            display: table;
            width: 100%;
            margin-top: 18px;
            padding-top: 12px;
            border-top: 1px solid #e0e0e0;
        }
        .footer-left {
            display: table-cell;
            width: 50%;
            font-size: 9px;
            color: #888;
            vertical-align: bottom;
        }
        .footer-right {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: middle;
        }
        .footer-brand {
            font-size: 13px;
            font-weight: bold;
            color: #287747;
        }
        .footer-thanks {
            font-size: 9px;
            color: #888;
            margin-top: 2px;
        }
        .footer-stamp {
            font-size: 8px;
            color: #aaa;
            margin-top: 14px;
        }
    </style>
</head>
<body>

{{-- ══════════════════ HEADER ══════════════════ --}}
<div class="header">
    <div class="header-left">
{{--        <img src="{{'images/logoSenBio.png'}}" class="logo" alt="BioSen100">--}}
        <div class="company-name">BIOSEN100</div>
        <div class="company-sub">{{ config('app.company_address', 'Dakar, Sénégal, Yoff') }}</div>
        <div class="company-sub">{{ config('app.company_phone', '77 451 03 13') }}</div>
    </div>
    <div class="header-right">
        <div class="invoice-title">FACTURE</div>
        <div class="invoice-number">{{ $orderNumber }}</div>
        <div class="invoice-date">Date d'émission : {{ $date }}</div>
    </div>
</div>

{{-- ══════════════════ INFO CLIENT ══════════════════ --}}
<div class="info-band">
    <div class="info-band-left">
        <div class="info-label">Émit</div>
        <div class="info-value">{{ strtoupper(($commande->prenom_client ?? '') . ' ' . ($commande->nom_client ?? '')) }}</div>
        @if($commande->telephone_client)
            <div class="info-sub">{{ $commande->telephone_client }}</div>
        @endif
        @if($commande->adresse_client)
            <div class="info-sub">{{ $commande->adresse_client }}</div>
        @endif
        @if($commande->pays)
            <div class="info-sub">{{ $commande->pays }}</div>
        @endif
    </div>
    <div class="info-band-right">
        <div class="info-label">Réf. commande</div>
        <div class="info-value" style="color:#287747;">{{ $orderNumber }}</div>
    </div>
</div>

{{-- ══════════════════ REFS ══════════════════ --}}
<div class="refs-table">
    <div class="refs-row">
        <div class="refs-cell-label">Zone / Ville</div>
        <div class="refs-cell-value">{{ $commande->ville_zone ?: ($commande->pays ?? '—') }}</div>
    </div>
    <div class="refs-row">
        <div class="refs-cell-label">Tél. livraison</div>
        <div class="refs-cell-value">{{ $livraison->telephone ?? $commande->telephone_client ?? '—' }}</div>
    </div>
</div>

{{-- ══════════════════ PRODUITS ══════════════════ --}}
<table class="products-table">
    <thead>
    <tr>
        <th>Désignation</th>
        <th class="text-center" style="width:60px;">Qté</th>
        <th class="text-right" style="width:110px;">Prix unit.</th>
        <th class="text-right" style="width:110px;">Montant</th>
    </tr>
    </thead>
    <tbody>
    @forelse($produits as $index => $produit)
        <tr>
            <td>
                <span class="num-badge">{{ $index + 1 }}</span>
                <span>{{ $produit['nom'] ?? $produit['name'] ?? 'Produit' }}</span>
            </td>
            <td class="text-center">{{ $produit['quantite'] ?? $produit['quantity'] ?? 1 }}</td>
            <td class="text-right">{{ number_format($produit['prix_unitaire'] ?? $produit['price'] ?? 0, 0, ',', ' ') }} F</td>
            <td class="text-right" style="color:#287747;">{{ number_format($produit['total'] ?? 0, 0, ',', ' ') }} F</td>
        </tr>
    @empty
        <tr>
            <td colspan="5" style="text-align:center; color:#aaa; padding:20px;">Aucun produit</td>
        </tr>
    @endforelse
    </tbody>
</table>

{{-- ══════════════════ TOTAUX ══════════════════ --}}
@php
    $frais    = $livraison->frais ?? 0;
    $sousTotal = ($commande->montantTotal ?? 0) - $frais;
    // Conversion du total en lettres (simplifié)
    $totalInt = intval($commande->montantTotal ?? 0);
    $milliers = floor($totalInt / 1000);
    $reste    = $totalInt % 1000;
    $enLettres = '';
    $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
              'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf', 'vingt'];
    if ($milliers > 0 && $milliers <= 20) {
        $enLettres = ($milliers == 1 ? 'mille' : $units[$milliers] . ' mille');
        if ($reste > 0 && $reste <= 20) $enLettres .= ' ' . $units[$reste];
    } else {
        $enLettres = number_format($totalInt, 0, ',', ' ');
    }
    $enLettres = strtoupper($enLettres);
@endphp

<div class="totals-wrapper">
    <div class="totals-left">
        <div class="amount-in-words">
            Arrêtée la présente facture à la somme de francs <strong>{{ $enLettres }}</strong>
        </div>
    </div>
    <div class="totals-right">
        <div class="totals-box">
            <div class="totals-row">
                <div class="totals-row-label">Sous-total</div>
                <div class="totals-row-value">{{ number_format($sousTotal, 0, ',', ' ') }} FCFA</div>
            </div>
            <div class="totals-row">
                <div class="totals-row-label">Frais de livraison</div>
                <div class="totals-row-value">{{ number_format($frais, 0, ',', ' ') }} FCFA</div>
            </div>
            <div class="totals-grand">
                <div class="totals-grand-label">Total à payer</div>
                <div class="totals-grand-value">
                    {{ number_format($commande->montantTotal ?? 0, 0, ',', ' ') }}
                    <span class="totals-grand-currency">FCFA</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════ CONDITIONS ══════════════════ --}}
<div class="conditions-section">
    <div class="conditions-title">Conditions</div>
    <div class="conditions-text">
        Échange possible avant utilisation du produit. Aucun retour après utilisation.
    </div>
</div>

{{-- ══════════════════ FOOTER ══════════════════ --}}
<div class="footer">
    <div class="footer-left">
        <div>RC : SN DKR 2022 A 6647 &nbsp;&nbsp; NINEA : 009221079</div>
        <div>Compte bancaire UBA : 309070004683</div>
    </div>
    <div class="footer-right">
        <div class="footer-brand">BIOSEN100</div>
        <div class="footer-thanks">Merci de votre confiance</div>
    </div>
</div>

<div class="footer-stamp">
    Document généré le {{ $date }} — {{ $orderNumber }}
</div>

</body>
</html>
