<?php

namespace App\Modules\Lead\Events;

use App\Modules\Lead\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Tags adicionadas ao lead (ids internos). */
class LeadTagged
{
    use Dispatchable;
    use SerializesModels;

    /** @param list<int> $tagIds */
    public function __construct(public Lead $lead, public array $tagIds) {}
}
