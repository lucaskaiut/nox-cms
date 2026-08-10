<?php

namespace App\Modules\AiPublisher\Services;

use App\Modules\AiPublisher\Models\AiContentJob;
use App\Modules\AiPublisher\Models\AiPublisherSettings;
use App\Modules\Post\Events\PostCreated;
use App\Modules\Post\Events\PostPublished;
use App\Modules\Post\Models\Post;
use App\Modules\Post\Models\Tag;
use App\Modules\Post\Models\Category;
use App\Modules\User\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AiPublishingService
{
    private int $minContentLength;
    private string $defaultStatus;

    public function __construct()
    {
        $settings = AiPublisherSettings::query()->first();

        $this->minContentLength = $settings?->min_content_length ?? (int) config('ai-publisher.min_content_length', 200);
        $this->defaultStatus = $settings?->default_status ?? config('ai-publisher.default_status', 'draft');
    }

    public function publish(array $data): Post
    {
        $this->validate($data);
        $excerpt = $data['excerpt'] ?? $this->generateExcerpt($data['content']);
        $status = $data['status'] ?? $this->defaultStatus;

        return DB::transaction(function () use ($data, $excerpt, $status): Post {
            $job = AiContentJob::query()->create([
                'type' => 'post', 'topic' => $data['title'],
                'requested_by' => $data['requested_by'] ?? null,
                'source' => $data['source'] ?? 'ai', 'status' => 'processing',
                'payload' => $data, 'started_at' => now(),
            ]);

            try {
                $post = Post::query()->create([
                    'title' => $data['title'], 'slug' => Str::slug($data['title']),
                    'excerpt' => $excerpt, 'content' => $data['content'],
                    'status' => $status, 'reading_time' => $this->estimateReadingTime($data['content']),
                    'meta_title' => $data['meta_title'] ?? $this->generateMetaTitle($data['title']),
                    'meta_description' => $data['meta_description'] ?? $this->generateMetaDescription($excerpt),
                    'featured_image' => $data['featured_image'] ?? null,
                    'og_title' => $data['og_title'] ?? null,
                    'og_description' => $data['og_description'] ?? null,
                    'og_image' => $data['og_image'] ?? null,
                    'published_at' => $status === 'published' ? now() : null,
                    'author_id' => auth()->id() ?? User::query()->first()?->getKey(),
                ]);

                if (! empty($data['tags'])) {
                    $ids = [];
                    foreach ($data['tags'] as $name) {
                        $tag = Tag::query()->firstOrCreate(['name' => $name], ['slug' => Str::slug($name)]);
                        $ids[] = $tag->getKey();
                    }
                    $post->tags()->sync($ids);
                }

                if (isset($data['category'])) {
                    $category = Category::query()->where('name', $data['category'])->orWhere('slug', Str::slug($data['category']))->first();
                    if ($category) $post->categories()->sync([$category->getKey()]);
                }

                PostCreated::dispatch($post);

                if ($post->status === 'published') {
                    PostPublished::dispatch($post);
                }

                $job->markCompleted($post->getKey(), ['title' => $post->title, 'slug' => $post->slug]);

                return $post->load(['author', 'categories', 'tags']);
            } catch (\Throwable $e) { $job->markFailed($e->getMessage()); throw $e; }
        });
    }

    private function validate(array $data): void
    {
        if (blank($data['title'] ?? null)) throw new \InvalidArgumentException('O título é obrigatório.');
        if (blank($data['content'] ?? null)) throw new \InvalidArgumentException('O conteúdo é obrigatório.');
        if (Str::length(strip_tags($data['content'])) < $this->minContentLength) throw new \InvalidArgumentException("O conteúdo deve ter no mínimo {$this->minContentLength} caracteres.");
    }

    private function generateExcerpt(string $content, int $max = 400): string { return rtrim(Str::limit(strip_tags($content), $max), ' .,;:-'); }
    private function estimateReadingTime(string $content): int { return (int) max(1, ceil(str_word_count(strip_tags($content)) / 200)); }
    private function generateMetaTitle(string $title): string { return Str::limit($title, 160, ''); }
    private function generateMetaDescription(string $excerpt): string { return Str::limit($excerpt, 320, ''); }
}
