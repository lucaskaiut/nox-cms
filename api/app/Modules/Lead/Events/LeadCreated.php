<?php

namespace App\Modules\Lead\Events;

use App\Modules\Lead\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Lead novo (site, WhatsApp ou cadastro manual). */
class LeadCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Lead $lead) {}
}
