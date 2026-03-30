<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Student\Jobs\SendCertificateEmailJob;
use App\Student\Models\Certificate;
use App\Student\Models\IssuedCertificate;
use App\Student\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;
use setasign\Fpdi\Fpdi; // La librería maestra para leer y escribir PDFs

class GenerateCertificateController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'dni' => 'required|exists:students,dni',
            'certificate_id' => 'required|exists:certificates,id',
            'certificate_code' => 'required|string',
        ]);

        try {
            $existingCertificate = IssuedCertificate::where('certificate_code', $request->certificate_code)->first();
            if ($existingCertificate) {
                return response()->json([
                    'message' => 'La constancia ya existe. Recuperando del historial...',
                    'data' => $existingCertificate,
                    'download_url' => url('/api/certificates/download-generated/' . $existingCertificate->id)
                ]);
            }

            $student = Student::where('dni', $request->dni)->firstOrFail();
            $certificate = Certificate::findOrFail($request->certificate_id);

            // Validamos que la plantilla PDF exista
            if (!Storage::exists($certificate->file_path)) {
                return response()->json(['message' => 'El archivo de la plantilla PDF no existe.'], 404);
            }

            $certificatePath = Storage::path($certificate->file_path);

            // --- 1. PREPARAR EL CÓDIGO QR ---
            $trackingCode = Str::random(10);
            $validationUrl = "https://constancias.uprit.edu.pe/validar/" . $trackingCode;
            
            $qrUrl = "https://quickchart.io/qr?size=150&text=" . urlencode($validationUrl);
            $qrTempPath = storage_path('app/temp_qr_' . time() . '.png');

            try {
                $response = Http::get($qrUrl);
                if ($response->successful()) {
                    file_put_contents($qrTempPath, $response->body());
                }
            } catch (\Exception $e) {
                Log::error("Error descargando QR desde QuickChart: " . $e->getMessage());
            }

            // --- 2. MAGIA DE FPDI (ESCRIBIR SOBRE LA PLANTILLA PDF) ---
            $pdf = new Fpdi();
            
            // Cargamos la plantilla original
            $pdf->setSourceFile($certificatePath);
            $templateId = $pdf->importPage(1);

            // Agregamos una nueva página A4 Vertical ('P') o Horizontal ('L')
            $pdf->AddPage('P', 'A4');
            $pdf->useTemplate($templateId, 0, 0, 210, 297); // 210x297mm es el tamaño A4

            // --- 3. DIBUJAR LOS TEXTOS EN COORDENADAS (X, Y) ---
            // Nota: Se usa utf8_decode para que las tildes y las Ñ se impriman correctamente
            
            $pdf->SetTextColor(0, 0, 0); // Color Negro

            // Nombres y Apellidos
            $pdf->SetFont('Arial', 'B', 14); // B = Bold (Negrita), Tamaño 14
            $pdf->SetXY(50, 100); // <-- AJUSTA ESTOS VALORES (Milímetros desde la izquierda, Milímetros desde arriba)
            $pdf->Write(0, utf8_decode($student->name . ' ' . $student->surname));

            $pdf->SetFont('Arial', '', 12); // Quitamos la negrita y bajamos tamaño a 12

            // DNI
            $pdf->SetXY(50, 115); // <-- Ajusta Y para bajar de renglón
            $pdf->Write(0, utf8_decode($student->dni));

            // Programa
            $pdf->SetXY(50, 125); // <-- Ajusta Y
            $pdf->Write(0, utf8_decode($student->program));

            // Periodo
            $pdf->SetXY(50, 135); // <-- Ajusta Y
            $pdf->Write(0, utf8_decode($student->period));

            // Fecha de Emisión
            Carbon::setLocale('es');
            $fechaEmision = Carbon::now()->translatedFormat('d \d\e F \d\e\l Y');
            $pdf->SetXY(130, 200); // <-- Ajusta X e Y para ponerlo abajo a la derecha
            $pdf->Write(0, utf8_decode(ucfirst($fechaEmision)));

            // --- 4. INSERTAR EL CÓDIGO QR ---
            if (file_exists($qrTempPath)) {
                // Image(ruta, X, Y, Ancho_mm, Alto_mm)
                // Ej: 150mm desde la izquierda, 230mm desde arriba, de 30x30 milímetros
                $pdf->Image($qrTempPath, 150, 230, 30, 30); 
            }

            // --- 5. GUARDAR EL PDF FINAL ---
            $fileName = 'CONSTANCIA_' . $student->dni . '_' . time() . '.pdf';
            $relativeSavePath = 'generated_certificates/' . $fileName;
            
            Storage::makeDirectory('generated_certificates');
            $absoluteSavePath = Storage::path($relativeSavePath);
            
            // 'F' indica que se guarde como archivo en el disco
            $pdf->Output('F', $absoluteSavePath); 

            // --- 6. LIMPIEZA DEL SERVIDOR ---
            if (file_exists($qrTempPath)) {
                unlink($qrTempPath);
            }

            // --- 7. REGISTRO EN LA BASE DE DATOS ---
            $issued = IssuedCertificate::create([
                'certificate_id' => $certificate->id,
                'student_code' => $student->dni,
                'certificate_code' => $request->certificate_code,
                'file_path' => $relativeSavePath,
                'tracking_code' => $trackingCode
            ]);

            return response()->json([
                'message' => 'Constancia generada y convertida a PDF con éxito.',
                'data' => $issued,
                'download_url' => url('/api/certificates/download-generated/' . $issued->id)
            ]);

        } catch (Exception $e) {
            Log::error("Error generando constancia FPDI: " . $e->getMessage());

            if (isset($qrTempPath) && file_exists($qrTempPath)) {
                unlink($qrTempPath);
            }

            return response()->json([
                'message' => 'Error al generar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadGenerated(IssuedCertificate $issuedCertificate)
    {
        if (!Storage::exists($issuedCertificate->file_path)) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }
        return Storage::download($issuedCertificate->file_path);
    }

    public function sendEmail(Request $request, IssuedCertificate $issuedCertificate)
    {
        $request->validate([
            'email' => 'required|email',
            'cc_emails' => 'nullable|array',
            'cc_emails.*' => 'email',
            'body' => 'required|string'
        ]);

        $student = Student::where('dni', $issuedCertificate->student_code)->first();

        SendCertificateEmailJob::dispatch(
            $student,
            $issuedCertificate,
            $request->email,
            $request->cc_emails,
            $request->body
        );

        return response()->json([
            'message' => 'El envío de correo se ha puesto en cola exitosamente.'
        ], 202);
    }
}