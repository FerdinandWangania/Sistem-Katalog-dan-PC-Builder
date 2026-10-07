<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email verifikasi akun berisi token.
 */
class VerifyEmailMail extends Mailable
{
    public function __construct(
        public string $token,
        public string $name,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verifikasi Email Akun PC Store');
    }

    public function content(): Content
    {
        $token = e($this->token);
        $name = e($this->name);

        return new Content(htmlString: <<<HTML
            <p>Halo {$name},</p>
            <p>Terima kasih sudah mendaftar di PC Store. Kode verifikasi email kamu:</p>
            <p style="font-size:24px;font-weight:bold;letter-spacing:4px">{$token}</p>
            <p>Kirim ke <b>POST /api/verify-email</b> dengan email + token ini.</p>
            HTML);
    }
}
