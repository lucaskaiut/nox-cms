<?php

namespace App\Modules\Crm\Http\Requests;

use App\Modules\Crm\Enums\StageOutcome;
use App\Modules\Crm\Models\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePipelineStageRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:60'],
            'color' => [$required, 'string', Rule::in(PipelineStage::COLORS)],
            'outcome' => [$required, 'string', Rule::enum(StageOutcome::class)],
        ];
    }
}
