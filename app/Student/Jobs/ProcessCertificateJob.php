<?php

namespace App\Student\Jobs;

use App\Student\Mail\CertificateMail;
use App\Student\Models\Certificate;
use App\Student\Models\Student;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpWord\TemplateProcessor;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ProcessCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $payload)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $student = Student::firstOrCreate(
            ['student_code' => $this->payload['student_code']],
            [
                'document_number' => $this->payload['document_number'],
                'full_name' => $this->payload['full_name'],
                'program' => $this->payload['program'],
                'email' => $this->payload['email']
            ]
        );

        $trackingCode = 'C-' . strtoupper(substr(uniqid(), -5)) . '-UPRIT';

        $certificate = Certificate::create([
            'student_id' => $student->id,
            'tracking_code' => $trackingCode,
            'status' => 'processing'
        ]);

        try {
            // 1. Procesar Word
            $template = new TemplateProcessor(storage_path('app/templates/base_template.docx'));
            $template->setValue('full_name', $student->full_name);
            $template->setValue('document_number', $student->document_number);
            $template->setValue('student_code', $student->student_code);
            $template->setValue('program', $student->program);

            \Carbon\Carbon::setLocale('es');
            $template->setValue('date', \Carbon\Carbon::now()->translatedFormat('j \d\e F \d\e Y'));

            $docPath = 'certificates/' . $trackingCode . '.docx';
            $template->saveAs(storage_path('app/' . $docPath));

            // 2. Generar QR
            $qrUrl = config('app.url') . '/api/verify/' . $trackingCode;
            $qrPath = storage_path('app/qrcodes/' . $trackingCode . '.png');
            QrCode::format('png')->size(100)->margin(1)->generate($qrUrl, $qrPath);

            $certificate->update(['file_path' => $docPath]);

            // 3. Enviar Correo
            Mail::to($student->email)->send(new CertificateMail($certificate, $qrPath));

            $certificate->update(['status' => 'completed']);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Certificate Error: ' . $e->getMessage());
            $certificate->update(['status' => 'failed']);
        }
    }
}
