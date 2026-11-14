<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Mail\LeadEmail;
use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Crm\Models\LeadActivity;
use App\Modules\Crm\Support\LeadTemplate;
use App\Modules\Lead\Models\Lead;
use App\Modules\Post\Models\Post;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Envia e-mails ao lead (resposta automática, nutrição) com nome da empresa
 * como remetente, resposta para o e-mail do tenant e link de descadastro.
 * Registra o envio na linha do tempo do lead.
 */
class LeadMailer
{
    public function __construct(private readonly PipelineService $pipeline) {}

    public function send(Lead $lead, string $subject, string $body, ?Post $post = null, array $meta = []): bool
    {
        if (! $lead->canReceiveEmail()) {
            return false;
        }

        $settings = CrmSettings::current($lead->tenant_id);
        $tenant = Tenant::query()->find($lead->tenant_id);
        $subject = LeadTemplate::render($subject, $lead);
        $body = LeadTemplate::render($body, $lead);
        $postData = null;
        if ($post && ($url = $settings->postUrl($post->slug))) {
            $postData = ['title' => $post->title, 'excerpt' => $post->excerpt, 'url' => $this->withUtm($url, $meta['utm_campaign'] ?? 'nutricao')];
        }

        Mail::to($lead->email, $lead->name)->send(new LeadEmail(
            subjectLine: $subject,
            body: $body,
            signature: $settings->email_signature,
            unsubscribeUrl: $this->unsubscribeUrl($lead),
            replyToAddress: $tenant?->email,
            fromName: $tenant?->name,
            post: $postData,
        ));

        $this->pipeline->log($lead, 'email_sent', $subject, $meta);

        return true;
    }

    public function unsubscribeUrl(Lead $lead): string
    {
        return URL::signedRoute('leads.unsubscribe', ['lead' => $lead->uuid]);
    }

    private function withUtm(string $url, string $campaign): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'utm_source' => 'email', 'utm_medium' => 'email', 'utm_campaign' => $campaign,
        ]);
    }
}
