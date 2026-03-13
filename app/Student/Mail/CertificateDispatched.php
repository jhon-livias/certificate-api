<?php

namespace App\Student\Mail;

use App\Student\Models\IssuedCertificate;
use App\Student\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;


class CertificateDispatched extends Mailable
{
    use Queueable, SerializesModels;

    public $student;
    public $issuedCertificate;
    public $bodyText;
    public $validationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Student $student, IssuedCertificate $issuedCertificate, $bodyText)
    {
        $this->student = $student;
        $this->issuedCertificate = $issuedCertificate;
        $this->bodyText = $bodyText;

        $frontendUrl = config('app.fe_url');
        $this->validationUrl = $frontendUrl . '/validar/' . $issuedCertificate->tracking_code;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Entrega de Constancia - UPRIT',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.certificate',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            // CORRECCIÓN AQUÍ: Usamos Storage::path() y $this->issuedCertificate
            // Attachment::fromPath(Storage::path($this->issuedCertificate->file_path))
            //     ->as($this->issuedCertificate->certificate_code . '.docx')
            //     ->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];
    }
}
