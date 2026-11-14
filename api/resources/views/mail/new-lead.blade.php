<x-mail::message>
# Novo lead: {{ $lead->name ?: $lead->ref }}

@if($lead->company)**Empresa:** {{ $lead->company }}  
@endif
@if($lead->phone)**Telefone:** {{ $lead->phone }}  
@endif
@if($lead->email)**E-mail:** {{ $lead->email }}  
@endif
**Canal:** {{ ['form' => 'Formulário', 'whatsapp' => 'WhatsApp', 'manual' => 'Cadastro manual'][$lead->channel] ?? $lead->channel }}  
@if($lead->post)**Post de origem:** {{ $lead->post->title }}  
@endif
@if($lead->cta)**Oferta:** {{ $lead->cta }}  
@endif
@if($lead->gclid || $lead->gbraid || $lead->wbraid)**Origem:** Google Ads{{ $lead->utm_term ? ' ('.$lead->utm_term.')' : '' }}  
@endif

@if($lead->message)
<x-mail::panel>
{{ $lead->message }}
</x-mail::panel>
@endif

Responder rápido aumenta muito a chance de fechar.

<x-mail::button :url="$url">
Abrir no CRM
</x-mail::button>
</x-mail::message>
