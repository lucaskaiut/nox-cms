<?php

namespace App\Modules\Crm\Mail;

use App\Modules\Lead\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewLeadMail extends Mailable
{
    use Queueable;

    public function __construct(public Lead $lead, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Novo lead: '.($this->lead->name ?: $this->lead->ref));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-lead');
    }
}
