<?php

namespace App\Modules\Lead\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Lead\Models\Lead;
use App\Modules\User\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission(Permission::LEAD_READ); }
    public function view(User $user, Lead $lead): bool { return $user->tenant_id === $lead->tenant_id && $user->hasPermission(Permission::LEAD_READ); }
    public function create(User $user): bool { return $user->hasPermission(Permission::LEAD_CREATE); }
    public function update(User $user, Lead $lead): bool { return $user->tenant_id === $lead->tenant_id && $user->hasPermission(Permission::LEAD_UPDATE); }
}
