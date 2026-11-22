<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KbTag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(KbArticle::class, 'kb_article_tag');
    }
}
