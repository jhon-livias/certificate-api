<?php

namespace App\Student\Jobs;

use App\Student\Models\IssuedCertificate;
use App\Student\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Student\Mail\CertificateDispatched;

class SendCertificateEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $student;
    public $issuedCertificate;
    public $email;
    public $ccEmails;
    public $body;

    /**
     * Create a new job instance.
     */
    public function __construct(Student $student, IssuedCertificate $issuedCertificate, $email, $ccEmails, $body)
    {
        $this->student = $student;
        $this->issuedCertificate = $issuedCertificate;
        $this->email = $email;
        $this->ccEmails = $ccEmails;
        $this->body = $body;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $mail = Mail::to($this->email);

        if (!empty($this->ccEmails)) {
            $mail->cc($this->ccEmails);
        }

        $mail->send(new CertificateDispatched($this->student, $this->issuedCertificate, $this->body));
    }
}
