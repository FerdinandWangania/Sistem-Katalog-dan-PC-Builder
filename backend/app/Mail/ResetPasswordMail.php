<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email reset password berisi token (berlaku 1 jam).
 */
class ResetPasswordMail extends Mailable
{
    public function __construct(
        public string $token,
        public string $name,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset Password Akun PC Store');
    }

    public function content(): Content
    {
        $token = e($this->token);
        $name = e($this->name);

        return new Content(htmlString: <<<HTML
            <p>Halo {$name},</p>
            <p>Kode reset password kamu (berlaku 1 jam):</p>
            <p style="font-size:24px;font-weight:bold;letter-spacing:4px">{$token}</p>
            <p>Kirim ke <b>POST /api/reset-password</b> dengan email + token + password baru.</p>
            <p>Abaikan email ini bila kamu tidak memintanya.</p>
            HTML);
    }
}
