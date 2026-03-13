<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Student\Models\IssuedCertificate;
use App\Student\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ValidationController extends Controller
{
    public function validateCertificate(Request $request)
    {
        $request->validate([
            'tracking_code' => 'required|string',
            'dni' => 'required|string',
            'certificate_code' => 'required|string'
        ]);

        // Buscamos la constancia por su código único de BD y el código que digita el usuario
        $cert = IssuedCertificate::where('tracking_code', $request->tracking_code)
                    ->where('certificate_code', $request->certificate_code)
                    ->first();

        if (!$cert) {
            return response()->json(['message' => 'El Código de Constancia no existe o es incorrecto.'], 404);
        }

        // Buscamos si el DNI coincide con el estudiante dueño de esa constancia
        $student = Student::where('student_code', $cert->student_code)
                    ->where('document_number', $request->dni)
                    ->first();

        if (!$student) {
            return response()->json(['message' => 'El DNI no corresponde al titular de esta constancia.'], 403);
        }

        return response()->json([
            'message' => 'Constancia validada exitosamente.',
            'data' => [
                'student_name' => $student->fullName,
                'program' => $student->program,
                'issue_date' => $cert->creation_time->format('d/m/Y'),
                'certificate_code' => $cert->certificate_code
            ]
        ]);
    }

    public function downloadValidated(Request $request, $trackingCode)
    {
        $cert = IssuedCertificate::where('tracking_code', $trackingCode)
                    ->where('certificate_code', $request->query('code'))
                    ->firstOrFail();

        $student = Student::where('student_code', $cert->student_code)
                    ->where('document_number', $request->query('dni'))
                    ->firstOrFail();

        $absolutePath = Storage::path($cert->file_path);

        if (!file_exists($absolutePath)) {
            abort(404, 'El archivo físico no se encuentra disponible.');
        }

        // ¡EL ARREGLO ESTÁ AQUÍ! Limpiamos el código para que sea un nombre de archivo válido
        // Reemplazamos las barras (/) y backslashes (\) por un guion (-)
        $safeFileName = str_replace(['/', '\\'], '-', $cert->certificate_code) . '.docx';

        // Ahora sí lo enviamos a descargar con el nombre seguro
        return response()->download($absolutePath, $safeFileName);
    }
}
