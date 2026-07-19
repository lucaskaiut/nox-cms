<?php

namespace Database\Factories;

use App\Modules\Post\Models\Post;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'author_id' => User::factory(),
            'title' => fake()->sentence(),
            'slug' => fn (array $attrs) => \Illuminate\Support\Str::slug($attrs['title']),
            'excerpt' => fake()->sentences(3, true),
            'content' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
            'featured_image' => null,
            'featured_image_alt' => null,
            'status' => 'published',
            'meta_title' => null,
            'meta_description' => null,
            'published_at' => now(),
            'is_featured' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
