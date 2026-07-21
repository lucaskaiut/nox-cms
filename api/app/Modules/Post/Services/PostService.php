<?php

namespace App\Modules\Post\Services;

use App\Modules\Post\Models\Category;
use App\Modules\Post\Models\Post;
use App\Modules\Post\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PostService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Post::query()->with(['author', 'categories', 'tags'])
            ->when(Arr::get($filters, 'status'), fn ($q, $v) => $q->where('status', $v))
            ->when(Arr::get($filters, 'category'), fn ($q, $v) => $q->whereHas('categories', fn ($q) => $q->where('categories.id', $v)))
            ->when(Arr::get($filters, 'search'), fn ($q, $v) => $q->where(fn ($q) => $q->where('title', 'like', "%{$v}%")->orWhere('excerpt', 'like', "%{$v}%")))
            ->when(Arr::get($filters, 'slug'), fn ($q, $v) => $q->where('slug', $v))
            ->orderByDesc('created_at')->paginate(min(max($perPage, 1), 100));
    }

    public function create(array $data): Post
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);
        $data['author_id'] = auth()->id();
        $post = Post::query()->create(Arr::except($data, ['categories', 'tags']));
        $this->syncRelations($post, $data);

        return $post->load(['author', 'categories', 'tags']);
    }

    public function update(Post $post, array $data): Post
    {
        if (isset($data['title']) && ! isset($data['slug'])) $data['slug'] = Str::slug($data['title']);
        $post->fill(Arr::except($data, ['categories', 'tags'])); $post->save();
        $this->syncRelations($post, $data);

        return $post->refresh()->load(['author', 'categories', 'tags']);
    }

    public function delete(Post $post): void { $post->delete(); }

    private function syncRelations(Post $post, array $data): void
    {
        if (array_key_exists('categories', $data)) $post->categories()->sync((array) ($data['categories'] ?? []));
        if (array_key_exists('tags', $data)) {
            $ids = [];
            foreach (array_unique((array) ($data['tags'] ?? [])) as $name) {
                $tag = Tag::query()->firstOrCreate(['name' => $name], ['slug' => Str::slug($name)]);
                $ids[] = $tag->getKey();
            }
            $post->tags()->sync($ids);
        }
    }

    public function getSitemapEntries(): \Illuminate\Database\Eloquent\Collection
    {
        return Post::query()->where('status', 'published')->where('include_in_sitemap', true)
            ->where('published_at', '<=', now())->get(['id', 'uuid', 'slug', 'updated_at']);
    }
}
