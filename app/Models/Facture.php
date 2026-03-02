<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facture extends Model
{
    use HasFactory;

    protected $table = 'factures';

    protected $fillable = [
        'commande_id',
        'numero_facture',
        'date_emission',
        'date_echeance',
        'statut_paiement',
        'metadonnees',
    ];

    protected $casts = [
        'date_emission' => 'datetime',
        'date_echeance' => 'datetime',
        'metadonnees'   => 'array',
    ];

    /**
     * Relation : une facture appartient à une commande.
     */
    public function commande()
    {
        return $this->belongsTo(Commande::class);
    }
}