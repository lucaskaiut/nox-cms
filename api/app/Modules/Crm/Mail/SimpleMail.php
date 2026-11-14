<?php

namespace App\Modules\Crm\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SimpleMail extends Mailable
{
    use Queueable;

    public function __construct(public string $title, public string $text, public ?string $url = null, public ?string $urlLabel = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.simple');
    }
}
