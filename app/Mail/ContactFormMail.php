<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public string $email;
    public ?string $phone;
    public string $emailSubject;
    public string $clientMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(
        string $name,
        string $email,
        ?string $phone,
        string $emailSubject,
        string $clientMessage
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->emailSubject = $emailSubject;
        $this->clientMessage = $clientMessage;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
            replyTo: [$this->email],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-form',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'subject' => $this->emailSubject,
                'clientMessage' => $this->clientMessage,
                // Backward compatibility for view variables
                'firstName' => $this->name,
                'lastName' => '',
                'emailMessage' => $this->clientMessage,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}