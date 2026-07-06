<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $formData)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [$this->formData['email']],
            subject: 'New Contact Form Submission from ' . $this->formData['name'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact',
            with: [
                'name' => $this->formData['name'],
                'email' => $this->formData['email'],
                // NOT 'message': that key is reserved inside Mailable views (it's
                // the Illuminate\Mail\Message instance), so exposing the form
                // message as $message collides and throws when rendered.
                'messageBody' => $this->formData['message'],
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
