<?php

namespace App\Modules\AiPublisher\Http\Controllers;

use App\Modules\AiPublisher\Http\Requests\StoreAiPostRequest;
use App\Modules\AiPublisher\Services\AiPublishingService;
use App\Modules\AiPublisher\Services\DiscoveryGenerator;
use App\Modules\AiPublisher\Services\EditorialGuideService;
use App\Modules\AiPublisher\Services\SchemaGenerator;
use App\Modules\Post\Http\Resources\PostResource;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class AiPublisherController extends ApiController
{
    public function __construct(
        private readonly DiscoveryGenerator $discovery,
        private readonly SchemaGenerator $schema,
        private readonly EditorialGuideService $editorial,
        private readonly AiPublishingService $publisher,
    ) {}

    public function discovery(): JsonResponse { return $this->success($this->discovery->generate()); }
    public function docs(): JsonResponse { return $this->success($this->discovery->documentation()); }
    public function schemaPost(): JsonResponse { return $this->success($this->schema->forPost()); }
    public function schemaCategory(): JsonResponse { return $this->success($this->schema->forCategory()); }
    public function editorialGuide(): JsonResponse { return $this->success($this->editorial->get()); }

    public function publish(StoreAiPostRequest $request): JsonResponse
    {
        $post = $this->publisher->publish($request->validated());

        return $this->created(PostResource::make($post), "Post criado via IA. Status: {$post->status}.");
    }
}
