<?php

namespace App\Modules\Post\Console;

use App\Modules\Post\Enums\PostStatus;
use App\Modules\Post\Events\PostPublished;
use App\Modules\Post\Models\Post;
use App\Modules\Tenant\Support\TenantRunner;
use Illuminate\Console\Command;

/** Publica os posts agendados cuja data de publicação já chegou. */
class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publica posts com status "agendado" e data de publicação vencida';

    public function handle(TenantRunner $runner): int
    {
        $count = 0;
        Post::query()->withoutTenancy()
            ->where('status', PostStatus::SCHEDULED->value)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->orderBy('published_at')
            ->each(function (Post $post) use ($runner, &$count) {
                $runner->run($post->tenant_id, function () use ($post, &$count) {
                    $post->forceFill(['status' => PostStatus::PUBLISHED->value])->save();
                    PostPublished::dispatch($post->load(['author', 'categories', 'tags']));
                    $count++;
                });
            });

        if ($count) $this->info("{$count} post(s) publicado(s).");

        return self::SUCCESS;
    }
}
