<?php

namespace App\Modules\AiPublisher\Http\Requests;

use App\Modules\Post\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiPostRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'], 'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'meta_title' => ['nullable', 'string', 'max:160'], 'meta_description' => ['nullable', 'string', 'max:320'],
            'featured_image' => ['nullable', 'string', 'max:2048'],
            'og_title' => ['nullable', 'string', 'max:160'],
            'og_description' => ['nullable', 'string', 'max:320'],
            'og_image' => ['nullable', 'string', 'max:2048'],
            'category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'], 'tags.*' => ['string', 'max:100'],
            'source' => ['nullable', 'string', 'max:100'], 'requested_by' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::enum(PostStatus::class)],
        ];
    }
}
