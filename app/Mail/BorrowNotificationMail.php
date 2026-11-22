<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 通用的借用通知信：主旨與內文都已由 MailTemplate 渲染完成，這裡只負責送出。
 */
class BorrowNotificationMail extends Mailable
{
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.notification', with: ['body' => $this->body]);
    }
}
