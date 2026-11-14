<?php

namespace App\Modules\Lead\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $param = ['nullable', 'string', 'max:255', 'regex:/^[\w.~%:\/+=-]+$/'];
        $path = ['nullable', 'string', 'max:255', 'regex:/^\/[\w\-.\/]*$/'];

        return [
            'ref' => ['required', 'string', 'regex:/^NX-[2-9A-HJ-NP-Z]{6}$/'],
            'channel' => ['required', 'string', 'in:whatsapp,form'],
            'consent' => ['nullable', 'string', 'in:granted,denied,unknown'],
            // WhatsApp: só registra clique pago (sem click ID não há o que atribuir).
            // Formulário: registra qualquer visitante; o click ID é opcional.
            'gclid' => [...$param, Rule::requiredIf(fn () => $this->input('channel') === 'whatsapp'
                && ! $this->filled('gbraid') && ! $this->filled('wbraid'))],
            'gbraid' => $param, 'wbraid' => $param,
            'utm_source' => $param, 'utm_medium' => $param, 'utm_campaign' => $param,
            'utm_term' => $param, 'utm_content' => $param,
            'landing_path' => $path, 'click_path' => $path,
            // Post de origem (slug) e oferta/CTA clicada, enviados pelo site.
            'post_slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'cta' => ['nullable', 'string', 'max:120'],
            'captured_at' => ['nullable', 'date', 'before_or_equal:now'],
            'name' => ['exclude_unless:channel,form', 'required', 'string', 'max:120'],
            'email' => ['exclude_unless:channel,form', 'nullable', 'required_without:phone', 'email:rfc', 'max:160'],
            'phone' => ['exclude_unless:channel,form', 'nullable', 'required_without:email', 'string', 'max:30', 'regex:/^[\d\s()+.-]{8,30}$/'],
            'company' => ['exclude_unless:channel,form', 'nullable', 'string', 'max:120'],
            'message' => ['exclude_unless:channel,form', 'required', 'string', 'max:2000'],
        ];
    }
}
