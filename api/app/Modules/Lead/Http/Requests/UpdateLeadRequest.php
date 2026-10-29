<?php

namespace App\Modules\Lead\Http\Requests;

use App\Modules\Lead\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', 'string', Rule::enum(LeadStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'conversion_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }
}
