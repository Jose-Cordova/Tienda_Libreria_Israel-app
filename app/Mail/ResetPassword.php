<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    //Constructor, recibe el usuario y el token de restablecimiento
    public function __construct(public User $user, public string $token)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recuperación de contraseña',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        //Obtenemos la url base del frontend
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        //Construimos el enlace completo con el token
        $link = "{$frontendUrl}/reset-password?token={$this->token}&email=" . urlencode($this->user->email);


        return new Content(
            view: 'emails.reset_password',
            with: [
                'userName' => $this->user->name,
                'link' => $link,
                'expires' => now()->addMinutes(60)->format('d/m/Y H:i')
            ]
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
