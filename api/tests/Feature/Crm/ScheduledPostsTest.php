<?php

namespace Tests\Feature\Crm;

use App\Modules\Post\Events\PostPublished;
use App\Modules\Post\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithCrm;
use Tests\TestCase;

class ScheduledPostsTest extends TestCase
{
    use InteractsWithCrm;
    use RefreshDatabase;

    public function test_scheduled_posts_are_published_when_due(): void
    {
        $this->tenant = $this->createTenantWithRoles();
        $due = $this->createPost(['status' => 'scheduled', 'published_at' => now()->subMinute()]);
        $future = $this->createPost(['status' => 'scheduled', 'published_at' => now()->addDay()]);
        Event::fake([PostPublished::class]);

        $this->artisan('posts:publish-scheduled')->assertSuccessful();

        $this->assertSame('published', Post::withoutTenancy()->find($due->id)->status);
        $this->assertSame('scheduled', Post::withoutTenancy()->find($future->id)->status);
        Event::assertDispatched(PostPublished::class, 1);
    }

    public function test_scheduling_requires_date(): void
    {
        $this->tenant = $this->createTenantWithRoles();
        $this->actingAsAdmin();

        $this->postJson('/api/posts', ['title' => 'Agendado', 'status' => 'scheduled'])->assertUnprocessable()->assertJsonValidationErrors('published_at');
        $this->postJson('/api/posts', ['title' => 'Agendado', 'status' => 'scheduled', 'published_at' => now()->addDay()->toIso8601String(), 'cta_offer' => 'Diagnóstico'])
            ->assertCreated()->assertJsonPath('data.cta_offer', 'Diagnóstico');
    }
}
