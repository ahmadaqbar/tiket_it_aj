<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequest extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'ticket_id', 'feature', 'provider', 'model', 'status', 'input_tokens', 'output_tokens', 'duration_ms', 'error_code'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
