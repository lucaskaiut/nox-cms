<?php

namespace App\Modules\Post\Services;

use App\Modules\Post\Models\Category;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    public function list(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::query()
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, slug?: ?string, description?: ?string, parent_id?: ?int}  $data
     */
    public function create(array $data): Category
    {
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        return Category::query()->create($data);
    }

    /**
     * @param  array{name?: string, slug?: ?string, description?: ?string, parent_id?: ?int}  $data
     */
    public function update(Category $category, array $data): Category
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->fill($data);
        $category->save();

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
