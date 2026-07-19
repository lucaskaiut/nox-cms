<?php

namespace App\Modules\Post\Services;

use App\Modules\Post\Enums\PostStatus;
use App\Modules\Post\Models\Category;
use App\Modules\Post\Models\Post;
use App\Modules\Post\Models\Tag;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PostService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Post::query()
            ->with(['author', 'categories', 'tags'])
            ->when(Arr::get($filters, 'status'), fn ($q, $v) => $q->where('status', $v))
            ->when(Arr::get($filters, 'category'), fn ($q, $v) => $q->whereHas('categories', fn ($q) => $q->where('categories.id', $v)))
            ->when(Arr::get($filters, 'tag'), fn ($q, $v) => $q->whereHas('tags', fn ($q) => $q->where('tags.id', $v)))
            ->when(Arr::get($filters, 'author'), fn ($q, $v) => $q->where('author_id', $v))
            ->when(Arr::get($filters, 'featured'), fn ($q) => $q->where('is_featured', true))
            ->when(Arr::get($filters, 'published'), fn ($q) => $q->where('status', PostStatus::PUBLISHED->value))
            ->when(Arr::get($filters, 'search'), function ($q, $v): void {
                $q->where(function ($q) use ($v): void {
                    $q->where('title', 'like', "%{$v}%")
                        ->orWhere('excerpt', 'like', "%{$v}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Post
    {
        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data['title']);
        $data['author_id'] = auth()->id();

        $post = Post::query()->create(Arr::except($data, ['categories', 'tags']));

        $this->syncRelations($post, $data);

        return $post->load(['author', 'categories', 'tags']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Post $post, array $data): Post
    {
        if (isset($data['title']) && ! isset($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['title'], $post);
        }

        $post->fill(Arr::except($data, ['categories', 'tags']));
        $post->save();

        $this->syncRelations($post, $data);

        return $post->refresh()->load(['author', 'categories', 'tags']);
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }

    private function uniqueSlug(string $title, ?Post $exclude = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $counter = 1;

        while (Post::query()->withoutTenancy()->where('tenant_id', TenantContext::tenantId())->where('slug', $slug)->when($exclude, fn ($q) => $q->where('id', '!=', $exclude->getKey()))->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Post $post, array $data): void
    {
        if (array_key_exists('categories', $data)) {
            $post->categories()->sync((array) ($data['categories'] ?? []));
        }

        if (array_key_exists('tags', $data)) {
            $tagIds = $this->syncTags($data['tags'] ?? []);

            $post->tags()->sync($tagIds);
        }
    }

    /**
     * @param  list<string>  $tagNames
     * @return list<int>
     */
    private function syncTags(array $tagNames): array
    {
        $ids = [];

        foreach (array_unique($tagNames) as $name) {
            $tag = Tag::query()->firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name)],
            );

            $ids[] = $tag->getKey();
        }

        return $ids;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Post>
     */
    public function getSitemapEntries(): \Illuminate\Database\Eloquent\Collection
    {
        return Post::query()
            ->where('status', PostStatus::PUBLISHED->value)
            ->where('include_in_sitemap', true)
            ->where('published_at', '<=', now())
            ->get(['id', 'uuid', 'slug', 'updated_at']);
    }
}
