<x-mail::message>
{!! nl2br(e($body)) !!}

@if($post)
<x-mail::panel>
**{{ $post['title'] }}**  
@if(!empty($post['excerpt'])){{ $post['excerpt'] }}  
@endif
[Ler o artigo]({{ $post['url'] }})
</x-mail::panel>
@endif

@if($signature)
{!! nl2br(e($signature)) !!}
@endif

@if($unsubscribeUrl)
<x-slot:subcopy>
Não quer mais receber estes e-mails? [Descadastrar]({{ $unsubscribeUrl }})
</x-slot:subcopy>
@endif
</x-mail::message>
