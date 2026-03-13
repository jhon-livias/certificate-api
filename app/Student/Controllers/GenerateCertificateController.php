<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Student\Mail\CertificateDispatched;
use App\Student\Models\Certificate;
use App\Student\Models\IssuedCertificate;
use App\Student\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GenerateCertificateController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'student_code' => 'required|exists:students,student_code', // Verifica según tu campo en la BD
            'certificate_id' => 'required|exists:certificates,id',
            'certificate_code' => 'required|string', // El usuario confirmará el código en el front
        ]);

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

        // 1. Verificamos que el archivo físico de la plantilla exista
        if (!Storage::exists($certificate->file_path)) {
            return response()->json([
                'message' => 'El archivo de la plantilla no existe en el servidor.',
            ], 404);
        }

        $certificatePath = Storage::path($certificate->file_path);

        // 2. Inicializamos el procesador de Word
        $processor = new TemplateProcessor($certificatePath);

        // 3. Lógica de Género
        $isMale = strtoupper($student->gender) === 'M';

        // 4. Reemplazamos las variables (Diccionario)
        $processor->setValue('CODIGO_CONSTANCIA', $request->certificate_code);
        $processor->setValue('TITLE', $isMale ? 'el señor' : 'la señorita');
        $processor->setValue('FULL_NAME', $student->full_name);
        $processor->setValue('DNI', $student->document_number);
        $processor->setValue('STUDENT_CODE', $student->student_code);
        $processor->setValue('PROGRAM', $student->program);
        $processor->setValue('MODALITY', $student->modality);
        $processor->setValue('FACULTY', $student->campus); // O el campo que uses para facultad

        // Variables gramaticales
        $processor->setValue('ARTICLE', $isMale ? 'el' : 'la');
        $processor->setValue('REFERRED', $isMale ? 'referido' : 'referida');
        $processor->setValue('MATRICULADO', $isMale ? 'matriculado' : 'matriculada');
        $processor->setValue('DEL_INTERESADO', $isMale ? 'del interesado' : 'de la interesada');

        // Fechas y otros
        // Aquí puedes recibir el semestre por $request si quieres que el usuario lo escriba en el Front
        $processor->setValue('START_SEMESTER', $request->input('start_semester', '2025-I'));
        $processor->setValue('CURRENT_SEMESTER', $request->input('current_semester', '2025-II'));
        $processor->setValue('START_DATE', $request->input('start_date', '12 de Abril de 2025'));

        // Fecha actual en español
        Carbon::setLocale('es');
        $fechaEmision = Carbon::now()->translatedFormat('d \d\e F \d\e\l Y');
        $processor->setValue('FECHA_EMISION', ucfirst($fechaEmision));

        // 5. Guardar el nuevo documento
        $fileName = 'CONSTANCIA_' . $student->document_number . '_' . time() . '.docx';
        $relativeSavePath = 'generated_certificates/' . $fileName;

        Storage::makeDirectory('generated_certificates');
        $absoluteSavePath = Storage::path($relativeSavePath);
        $processor->saveAs($absoluteSavePath);

        // 6. Registrar en la Base de Datos
        $trackingCode = Str::random(10); // Código único para verificar la autenticidad luego
        $issued = IssuedCertificate::create([
            'certificate_id' => $certificate->id,
            'student_code' => $student->student_code,
            'certificate_code' => $request->certificate_code,
            'file_path' => $relativeSavePath,
            'tracking_code' => $trackingCode
        ]);

        // 7. Retornar éxito con la URL de descarga
        return response()->json([
            'message' => 'Constancia generada con éxito.',
            'data' => $issued,
            'download_url' => url('/api/certificates/download-generated/' . $issued->id)
        ]);
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

        $mail = Mail::to($request->email);

        if (!empty($request->cc_emails)) {
            $mail->cc($request->cc_emails);
        }

        $mail->send(new CertificateDispatched($student, $issuedCertificate, $request->body));

        return response()->json([
            'message' => 'Correo enviado exitosamente al estudiante.'
        ]);
    }
}
