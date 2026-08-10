<?php

namespace App\Modules\AiPublisher\Services;

class DiscoveryGenerator
{
    public function generate(): array
    {
        return [
            'name' => config('app.name', 'CMS'), 'version' => '1.0',
            'authentication' => 'Bearer Token',
            'resources' => ['posts' => true, 'categories' => true, 'tags' => true],
            'endpoints' => [
                'documentation' => url('/api/ai/docs'), 'schema_post' => url('/api/ai/schema/post'),
                'schema_category' => url('/api/ai/schema/category'), 'editorial_guide' => url('/api/ai/editorial-guide'),
                'publish' => url('/api/ai/posts'),
            ],
        ];
    }

    public function documentation(): array
    {
        return [
            'recommended_flow' => [
                '1. Call GET /api/ai/discovery to see available resources.',
                '2. Call GET /api/ai/schema/post to understand required/optional fields.',
                '3. Call GET /api/ai/editorial-guide for tone and content rules.',
                '4. Call GET /api/categories to list existing categories.',
                '5. Create a category via POST /api/categories if needed.',
                '6. Generate content following the editorial guide.',
                '7. Call POST /api/ai/posts to submit the post (status defaults to draft if omitted).',
                '8. To publish directly, set `status` to `published` in the payload.',
            ],
            'tips' => [
                'Use H2 and H3 headings.', 'Generate meta_description (max 320 chars).',
                'Content must be at least '.config('ai-publisher.min_content_length', 200).' characters.',
                'Excerpt is auto-generated if not provided.', 'Reading time is auto-estimated.',
            ],
        ];
    }
}
