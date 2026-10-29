<?php

namespace App\Modules\Lead\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $param = ['nullable', 'string', 'max:255', 'regex:/^[\w.~%:\/+=-]+$/'];
        $path = ['nullable', 'string', 'max:255', 'regex:/^\/[\w\-.\/]*$/'];

        return [
            'ref' => ['required', 'string', 'regex:/^NX-[2-9A-HJ-NP-Z]{6}$/'],
            'channel' => ['required', 'string', 'in:whatsapp'],
            'consent' => ['nullable', 'string', 'in:granted,denied,unknown'],
            'gclid' => [...$param, 'required_without_all:gbraid,wbraid'],
            'gbraid' => $param, 'wbraid' => $param,
            'utm_source' => $param, 'utm_medium' => $param, 'utm_campaign' => $param,
            'utm_term' => $param, 'utm_content' => $param,
            'landing_path' => $path, 'click_path' => $path,
            'captured_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
