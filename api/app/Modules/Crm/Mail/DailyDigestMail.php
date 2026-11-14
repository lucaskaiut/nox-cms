<?php

namespace App\Modules\Crm\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DailyDigestMail extends Mailable
{
    use Queueable;

    public function __construct(public array $digest, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Resumo do dia no CRM');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.daily-digest');
    }
}
