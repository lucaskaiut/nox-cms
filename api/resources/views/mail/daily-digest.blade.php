<x-mail::message>
# Resumo do dia

**{{ $digest['new_leads'] }}** lead(s) novo(s) ontem.

@if($digest['overdue']->isNotEmpty())
## Tarefas atrasadas ({{ $digest['overdue']->count() }})
@foreach($digest['overdue']->take(15) as $task)
- {{ $task->title }} — {{ $task->lead?->name ?: $task->lead?->ref }} (venceu {{ $task->due_at->timezone(config('leads.google_ads.timezone'))->format('d/m H:i') }})
@endforeach
@endif

@if($digest['today']->isNotEmpty())
## Para hoje ({{ $digest['today']->count() }})
@foreach($digest['today']->take(15) as $task)
- {{ $task->due_at->timezone(config('leads.google_ads.timezone'))->format('H:i') }} · {{ $task->title }} — {{ $task->lead?->name ?: $task->lead?->ref }}
@endforeach
@endif

@if($digest['stale']->isNotEmpty())
## Leads parados ({{ $digest['stale']->count() }})
@foreach($digest['stale']->take(15) as $lead)
- {{ $lead->name ?: $lead->ref }}{{ $lead->stage ? ' · '.$lead->stage->name : '' }} — sem atividade desde {{ ($lead->last_activity_at ?? $lead->created_at)->timezone(config('leads.google_ads.timezone'))->format('d/m') }}
@endforeach
@endif

<x-mail::button :url="$url">
Abrir tarefas
</x-mail::button>
</x-mail::message>
