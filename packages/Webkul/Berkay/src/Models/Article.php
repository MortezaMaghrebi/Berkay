<?php

namespace Webkul\Berkay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Webkul\Berkay\Contracts\Article as ArticleContract;

class Article extends Model implements ArticleContract
{
    /** Store records in the extension table. */
    protected $table = 'berkay_articles';

    /** Allow only explicitly managed business fields. */
    protected $fillable = ['title', 'slug', 'category', 'excerpt', 'body', 'meta_title', 'meta_description', 'status', 'published_at', 'cover_path', 'cover_alt'];

    /** Cast publication times for comparisons and forms. */
    protected $casts = ['published_at' => 'datetime'];

    /** Resolve an uploaded cover or a topic-specific editorial illustration. */
    public function getCoverUrlAttribute(): string
    {
        if ($this->cover_path) {
            return Storage::disk('public')->url($this->cover_path);
        }

        $image = match (true) {
            str_contains($this->slug, 'care'), str_contains($this->slug, 'storage') => 'editorial-care.webp',
            str_contains($this->slug, 'wallet') => 'wallet-sample.webp',
            str_contains($this->slug, 'color') => 'tote-sample.webp',
            str_contains($this->slug, 'crossbody') => 'hero-v2.webp',
            default => 'editorial-selection.webp',
        };

        return asset('berkay/'.$image);
    }
}
