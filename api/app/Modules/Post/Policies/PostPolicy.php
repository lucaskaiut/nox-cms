<?php

namespace App\Modules\Post\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Post\Models\Post;
use App\Modules\User\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::POST_READ);
    }

    public function view(User $user, Post $post): bool
    {
        return $this->sameTenant($user, $post)
            && $user->hasPermission(Permission::POST_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::POST_CREATE);
    }

    public function update(User $user, Post $post): bool
    {
        return $this->sameTenant($user, $post)
            && $user->hasPermission(Permission::POST_UPDATE);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->sameTenant($user, $post)
            && $user->hasPermission(Permission::POST_DELETE);
    }

    public function publish(User $user, Post $post): bool
    {
        return $this->sameTenant($user, $post)
            && $user->hasPermission(Permission::POST_PUBLISH);
    }

    private function sameTenant(User $user, Post $post): bool
    {
        return $user->tenant_id === $post->tenant_id;
    }
}
