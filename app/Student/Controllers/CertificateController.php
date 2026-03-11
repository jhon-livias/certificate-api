<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Student\Jobs\ProcessCertificateJob;
use App\Student\Jobs\ProcessStudentBulkJob;
use App\Student\Models\Certificate;
use App\Student\Models\Student;
use function Illuminate\Support\defer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CertificateController extends Controller
{
    public function student(string $code): JsonResponse
    {
        $student = Student::where('student_code', $code)->firstOrFail();
        return response()->json(['data' => $student]);
    }

    public function uploadBulk(Request $request)
    {
        $request->validate([
            'document' => [
                'required',
                'file',
                // Allow xlsx, csv, and occasionally txt (since some systems read CSVs as txt)
                'mimes:xlsx,csv,txt',
                // Explicitly allow the exact MIME types for Excel and CSVs
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain,application/csv,application/excel,application/vnd.ms-excel,application/vnd.msexcel'
            ]
        ]);

        $rows = Excel::toArray(new \stdClass, $request->file('document'))[0];

        // Enviamos TODO el array de filas al nuevo Job en segundo plano
        ProcessStudentBulkJob::dispatch($rows);

        return response()->json([
            'message' => 'La carga masiva de estudiantes ha comenzado en segundo plano.'
        ], 202);
    }

    // 2. Generación Individual desde el Menú 3
    public function generateSingle(Request $request)
    {
        $request->validate(['student_code' => 'required|exists:students,student_code']);

        $student = Student::where('student_code', $request->student_code)->first();

        $payload = [
            'student_code' => $student->student_code,
            'document_number' => $student->document_number,
            'full_name' => $student->full_name,
            'program' => $student->program,
            'email' => $student->email,
        ];

        // defer() manda esto al background inmediatamente después de responder al cliente
        defer(fn() => ProcessCertificateJob::dispatchSync($payload));

        return response()->json(['message' => 'Certificate generation started'], 202);
    }

    // 3. Endpoint de Validación Pública (QR)
    public function verify(string $trackingCode)
    {
        $certificate = Certificate::with('student')->where('tracking_code', $trackingCode)->firstOrFail();

        return response()->json([
            'valid' => $certificate->status === 'completed',
            'data' => [
                'tracking_code' => $certificate->tracking_code,
                'student_code' => $certificate->student->student_code,
                'full_name' => $certificate->student->full_name,
                'program' => $certificate->student->program,
            ]
        ]);
    }
}
