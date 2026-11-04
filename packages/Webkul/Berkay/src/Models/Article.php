<?php

namespace Webkul\Berkay\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Berkay\Contracts\Article as ArticleContract;

class Article extends Model implements ArticleContract
{
    /** Store records in the extension table. */
    protected $table = 'berkay_articles';

    /** Allow only explicitly managed business fields. */
    protected $fillable = ['title', 'slug', 'category', 'excerpt', 'body', 'meta_title', 'meta_description', 'status', 'published_at'];

    /** Cast publication times for comparisons and forms. */
    protected $casts = ['published_at' => 'datetime'];
}
