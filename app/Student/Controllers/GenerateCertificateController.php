<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Student\Jobs\SendCertificateEmailJob;
use App\Student\Models\Certificate;
use App\Student\Models\IssuedCertificate;
use App\Student\Models\Student;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GenerateCertificateController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'student_code' => 'required|exists:students,student_code', 
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

            $student = Student::where('student_code', $request->student_code)->firstOrFail();
            $certificate = Certificate::findOrFail($request->certificate_id);

            if (!Storage::exists($certificate->file_path)) {
                return response()->json(['message' => 'El archivo de la plantilla no existe.'], 404);
            }

            $certificatePath = Storage::path($certificate->file_path);
            $processor = new TemplateProcessor($certificatePath);

            $isMale = strtoupper($student->gender) === 'M';

            // Textos
            $processor->setValue('CODIGO_CONSTANCIA', $request->certificate_code);
            $processor->setValue('TITLE', $isMale ? 'el señor' : 'la señorita');
            $processor->setValue('FULL_NAME', $student->full_name);
            $processor->setValue('DNI', $student->document_number);
            $processor->setValue('STUDENT_CODE', $student->student_code);
            $processor->setValue('PROGRAM', $student->program);
            $processor->setValue('MODALITY', $student->modality);
            $processor->setValue('FACULTY', $student->campus); 

            $processor->setValue('ARTICLE', $isMale ? 'el' : 'la');
            $processor->setValue('REFERRED', $isMale ? 'referido' : 'referida');
            $processor->setValue('MATRICULADO', $isMale ? 'matriculado' : 'matriculada');
            $processor->setValue('DEL_INTERESADO', $isMale ? 'del interesado' : 'de la interesada');

            $processor->setValue('START_SEMESTER', $request->input('start_semester', 'NO_DATA'));
            $processor->setValue('CURRENT_SEMESTER', $request->input('current_semester', 'NO_DATA'));
            $processor->setValue('START_DATE', $request->input('start_date', 'NO_DATA'));

            Carbon::setLocale('es');
            $fechaEmision = Carbon::now()->translatedFormat('d \d\e F \d\e\l Y');
            $processor->setValue('FECHA_EMISION', ucfirst($fechaEmision));

            // --- MAGIA DEL QR BLINDADA ---
            $trackingCode = Str::random(10); 
            $validationUrl = "https://constancias.uprit.edu.pe/validar/" . $trackingCode;

            // 1. Usamos la API de Google con tamaño exacto de 120x120 pixeles
            $qrUrl = "https://chart.googleapis.com/chart?chs=120x120&cht=qr&chl=" . urlencode($validationUrl);
            $qrTempPath = storage_path('app/temp_qr_' . time() . '.png');
            
            // 2. Descargamos la imagen cruda
            $imgData = @file_get_contents($qrUrl);
            
            if ($imgData) {
                file_put_contents($qrTempPath, $imgData);
                
                // 3. LA CLAVE: Le pasamos SOLO la ruta cruda (sin arrays de width/height)
                $processor->setImageValue('QR_CODE', $qrTempPath);
            } else {
                // Si por alguna razón el VPS no tiene internet, al menos borramos la variable
                $processor->setValue('QR_CODE', 'NADA'); 
            }

            // 6. Guardar el nuevo documento fusionado
            $fileName = 'CONSTANCIA_' . $student->document_number . '_' . time() . '.docx';
            $relativeSavePath = 'generated_certificates/' . $fileName;

            Storage::makeDirectory('generated_certificates');
            $absoluteSavePath = Storage::path($relativeSavePath);
            $processor->saveAs($absoluteSavePath);

            // Limpieza de servidor
            if (file_exists($qrTempPath)) {
                unlink($qrTempPath);
            }

            $issued = IssuedCertificate::create([
                'certificate_id' => $certificate->id,
                'student_code' => $student->student_code,
                'certificate_code' => $request->certificate_code,
                'file_path' => $relativeSavePath,
                'tracking_code' => $trackingCode
            ]);

            return response()->json([
                'message' => 'Constancia generada con éxito.',
                'data' => $issued,
                'download_url' => url('/api/certificates/download-generated/' . $issued->id)
            ]);

        } catch (Exception $e) {
            // Si algo falla, lo guardamos en el log y se lo avisamos a Angular
            Log::error("Error generando constancia con QR: " . $e->getMessage());
            
            // Aseguramos borrar el QR si falló a la mitad del proceso
            if (isset($qrTempPath) && file_exists($qrTempPath)) {
                unlink($qrTempPath);
            }

            return response()->json([
                'message' => 'Error al generar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    // Método para descargar el archivo generado
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

        $student = Student::where('student_code', $issuedCertificate->student_code)->first();

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
