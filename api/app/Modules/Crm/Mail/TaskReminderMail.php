<?php

namespace App\Modules\Crm\Mail;

use App\Modules\Crm\Models\LeadTask;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TaskReminderMail extends Mailable
{
    use Queueable;

    public function __construct(public LeadTask $task, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Lembrete: '.$this->task->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.task-reminder');
    }
}
