<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GeneratedPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $generatedPassword;
    public string $userName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $userName, string $generatedPassword)
    {
        $this->userName = $userName;
        $this->generatedPassword = $generatedPassword;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Password Akun Member Brawijaya Gym',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.generated-password',
        );
    }
}
