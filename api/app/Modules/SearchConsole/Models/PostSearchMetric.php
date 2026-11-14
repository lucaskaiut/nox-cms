<?php

namespace App\Modules\SearchConsole\Models;

use App\Modules\Post\Models\Post;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cliques/impressões/posição média de um post num dia (Search Console). */
class PostSearchMetric extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'post_id', 'date', 'clicks', 'impressions', 'position'];

    protected function casts(): array
    {
        return ['date' => 'date', 'clicks' => 'integer', 'impressions' => 'integer', 'position' => 'float'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
