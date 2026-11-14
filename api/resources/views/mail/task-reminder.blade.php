<x-mail::message>
# {{ $task->title }}

Lead: **{{ $task->lead?->name ?: $task->lead?->ref }}**{{ $task->lead?->company ? ' ('.$task->lead->company.')' : '' }}  
Prazo: {{ $task->due_at->timezone(config('leads.google_ads.timezone'))->format('d/m/Y H:i') }}

@if($task->notes)
<x-mail::panel>
{{ $task->notes }}
</x-mail::panel>
@endif

<x-mail::button :url="$url">
Abrir lead
</x-mail::button>
</x-mail::message>
