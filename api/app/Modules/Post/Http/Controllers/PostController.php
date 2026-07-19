<?php

namespace App\Modules\Post\Http\Controllers;

use App\Modules\Post\Http\Requests\StorePostRequest;
use App\Modules\Post\Http\Requests\UpdatePostRequest;
use App\Modules\Post\Http\Resources\PostResource;
use App\Modules\Post\Models\Post;
use App\Modules\Post\Services\PostService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends ApiController
{
    public function __construct(private readonly PostService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        $posts = $this->service->paginate(
            $request->only(['status', 'category', 'tag', 'author', 'featured', 'published', 'search']),
            (int) $request->integer('per_page', 15),
        );

        return $this->paginated(PostResource::collection($posts));
    }

    public function show(Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        return $this->success(PostResource::make($post->load(['author', 'categories', 'tags'])));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $this->authorize('create', Post::class);

        $post = $this->service->create($request->validated());

        return $this->created(PostResource::make($post), 'Post criado com sucesso.');
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $post = $this->service->update($post, $request->validated());

        return $this->success(PostResource::make($post), 'Post atualizado com sucesso.');
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $this->service->delete($post);

        return $this->success(null, 'Post removido com sucesso.');
    }
}
