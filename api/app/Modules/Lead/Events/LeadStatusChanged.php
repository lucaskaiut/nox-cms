<?php

namespace App\Modules\Lead\Events;

use App\Modules\Lead\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Lead $lead, public ?LeadStatus $from) {}
}
