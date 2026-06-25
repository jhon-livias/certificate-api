<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Student\Jobs\SendCertificateEmailJob;
use App\Student\Models\Certificate;
use App\Student\Models\IssuedCertificate;
use App\Student\Models\Student;
use App\Student\Services\CertificateCodeService;
use App\Student\Services\DocumentPdfConverter;
use App\Student\Services\WordDocumentService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateCertificateController extends Controller
{
    public function __construct(
        protected WordDocumentService $wordDocumentService,
        protected CertificateCodeService $certificateCodeService,
        protected DocumentPdfConverter $documentPdfConverter,
    ) {
    }

    public function generate(Request $request)
    {
        $request->validate([
            'certificate_id' => 'required|exists:certificates,id',
            'certificate_code' => 'nullable|string',
            'dni' => 'nullable|string',
            'student_code' => 'nullable|string',
        ]);

        $identifier = $request->input('student_code') ?? $request->input('dni');
        if (!$identifier) {
            return response()->json(['message' => 'Debe enviar student_code o dni.'], 422);
        }

        try {
            $student = Student::query()
                ->where('document_number', $identifier)
                ->orWhere('student_code', $identifier)
                ->firstOrFail();

            $certificate = Certificate::findOrFail($request->certificate_id);
            $certificateCode = $request->certificate_code
                ?: $this->certificateCodeService->nextCode($certificate);

            $existingCertificate = IssuedCertificate::where('certificate_code', $certificateCode)->first();
            if ($existingCertificate) {
                return response()->json([
                    'message' => 'La constancia ya existe. Recuperando del historial...',
                    'data' => $existingCertificate,
                    'download_url' => url('/api/certificates/download-generated/' . $existingCertificate->id),
                ]);
            }

            if (!Storage::exists($certificate->file_path)) {
                return response()->json(['message' => 'El archivo de la plantilla no existe.'], 404);
            }

            $docxRelativePath = $this->wordDocumentService->fillStudentCertificate(
                templatePath: Storage::path($certificate->file_path),
                student: $student,
                certificateCode: $certificateCode,
            );

            $trackingCode = Str::random(10);
            $this->injectQrCode($docxRelativePath, $trackingCode);

            $pdfRelativePath = $this->wordDocumentService->convertToPdf(
                $docxRelativePath,
                $this->documentPdfConverter,
            );

            if (Storage::exists($docxRelativePath)) {
                Storage::delete($docxRelativePath);
            }

            $finalRelativePath = $pdfRelativePath;

            $issued = IssuedCertificate::create([
                'certificate_id' => $certificate->id,
                'student_code' => $student->document_number,
                'certificate_code' => $certificateCode,
                'file_path' => $finalRelativePath,
                'tracking_code' => $trackingCode,
            ]);

            return response()->json([
                'message' => 'Constancia generada con éxito.',
                'data' => $issued,
                'download_url' => url('/api/certificates/download-generated/' . $issued->id),
            ]);
        } catch (Exception $e) {
            Log::error('Error generando constancia: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error al generar documento: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadGenerated(IssuedCertificate $issuedCertificate)
    {
        if (!Storage::exists($issuedCertificate->file_path)) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        $safeFileName = str_replace(['/', '\\'], '-', $issuedCertificate->certificate_code) . '.pdf';

        return Storage::download(
            $issuedCertificate->file_path,
            $safeFileName,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function sendEmail(Request $request, IssuedCertificate $issuedCertificate)
    {
        $request->validate([
            'email' => 'required|email',
            'cc_emails' => 'nullable|array',
            'cc_emails.*' => 'email',
            'body' => 'required|string',
        ]);

        $student = Student::where('document_number', $issuedCertificate->student_code)->first();

        SendCertificateEmailJob::dispatch(
            $student,
            $issuedCertificate,
            $request->email,
            $request->cc_emails,
            $request->body
        );

        return response()->json([
            'message' => 'El envío de correo se ha puesto en cola exitosamente.',
        ], 202);
    }

    private function injectQrCode(string $docxRelativePath, string $trackingCode): void
    {
        $absoluteDocxPath = Storage::path($docxRelativePath);
        $zip = new \ZipArchive();
        $zip->open($absoluteDocxPath);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!str_contains($xml, 'QR_CODE')) {
            return;
        }

        $validationUrl = 'https://constancias.uprit.edu.pe/validar/' . $trackingCode;
        $qrUrl = 'https://quickchart.io/qr?size=150&text=' . urlencode($validationUrl);
        $qrTempPath = storage_path('app/temp_qr_' . time() . '.png');

        try {
            $response = Http::get($qrUrl);
            if (!$response->successful()) {
                return;
            }

            file_put_contents($qrTempPath, $response->body());

            $processor = new \PhpOffice\PhpWord\TemplateProcessor($absoluteDocxPath);
            $processor->setImageValue('QR_CODE', [
                'path' => $qrTempPath,
                'width' => 100,
                'height' => 100,
                'ratio' => false,
                'align' => 'right',
            ]);
            $processor->saveAs($absoluteDocxPath);
        } catch (Exception $e) {
            Log::warning('No se pudo inyectar QR en la constancia: ' . $e->getMessage());
        } finally {
            if (file_exists($qrTempPath)) {
                unlink($qrTempPath);
            }
        }
    }
}
