<?php

namespace App\Modules\Nurture\Models;

use App\Modules\Post\Models\Post;
use App\Modules\Shared\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um e-mail da sequência, enviado `delay_days` depois do passo anterior. */
class NurtureStep extends Model
{
    use HasUuid;

    protected $fillable = ['sequence_id', 'position', 'delay_days', 'subject', 'body', 'post_id'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'delay_days' => 'integer'];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(NurtureSequence::class, 'sequence_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
