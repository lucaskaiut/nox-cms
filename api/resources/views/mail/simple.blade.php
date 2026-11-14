<x-mail::message>
# {{ $title }}

{{ $text }}

@if($url)
<x-mail::button :url="$url">
{{ $urlLabel ?? 'Abrir' }}
</x-mail::button>
@endif
</x-mail::message>
