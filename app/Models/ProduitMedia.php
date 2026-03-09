<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProduitMedia extends Model
{
    use HasFactory;
    protected $table = 'produit_medias';
    protected $fillable = [
        'produit_id', 'type', 'chemin', 'url_externe',
        'titre', 'ordre', 'est_principal','type_categorie_id',
    ];
    protected $casts = [
        'est_principal' => 'boolean',
        'ordre'         => 'integer',
    ];

    public function produit() { return $this->belongsTo(Produit::class); }

    public function getUrlAttribute(): ?string
    {
        if ($this->type === 'video_url') return $this->url_externe;
        if ($this->chemin) return asset('storage/' . $this->chemin);
        return null;
    }

    public function getEmbedUrlAttribute(): ?string
    {
        if ($this->type !== 'video_url' || !$this->url_externe) return null;
        $url = trim($this->url_externe);
        if (str_contains($url, '/embed/') || str_contains($url, 'player.vimeo.com')) return $url;
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&modestbranding=1';
        }
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return null;
    }

    public function getYoutubeThumbnailAttribute(): ?string
    {
        if ($this->type !== 'video_url') return null;
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $this->url_externe, $m)) {
            return 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg';
        }
        return null;
    }

    public function isImage(): bool { return $this->type === 'image'; }
    public function isVideo(): bool { return $this->type === 'video_url'; }

    public function typeCategorie()
    {
        return $this->belongsTo(TypeCategorie::class);
    }

    public function scopeOfTypeCategorie($query, $typeCategorieId)
    {
        return $query->where('type_categorie_id', $typeCategorieId);
    }
}
