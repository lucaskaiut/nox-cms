<?php

namespace App\Modules\Crm\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail enviado ao lead (resposta automática e passos de nutrição). */
class LeadEmail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public ?string $signature = null,
        public ?string $unsubscribeUrl = null,
        public ?string $replyToAddress = null,
        public ?string $fromName = null,
        public ?array $post = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromName ? new Address((string) config('mail.from.address'), $this->fromName) : null,
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress)] : [],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.lead-email');
    }

    public function headers(): \Illuminate\Mail\Mailables\Headers
    {
        return new \Illuminate\Mail\Mailables\Headers(text: $this->unsubscribeUrl ? [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ] : []);
    }
}
