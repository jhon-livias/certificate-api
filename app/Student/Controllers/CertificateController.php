<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Shared\Foundation\Requests\GetAllRequest;
use App\Shared\Foundation\Resources\GetAllCollection;
use App\Shared\Foundation\Services\SharedService;
use App\Student\Jobs\ProcessCertificateJob;
use App\Student\Jobs\ProcessStudentBulkJob;
use App\Student\Models\Certificate;
use App\Student\Models\Student;
use App\Student\Resources\StudentResource;
use Illuminate\Support\Facades\Cache;
use function Illuminate\Support\defer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function __construct(
        protected SharedService $sharedService,
    ) {
    }

    public function student(string $code): JsonResponse
    {
        $student = Cache::remember("student_{$code}", now()->addHours(24), function () use ($code) {
            return Student::where('student_code', $code)->firstOrFail();
        });
        return response()->json(['data' => $student]);
    }

    public function students(GetAllRequest $request): JsonResponse
    {
        $query = Cache::remember(
            key: 'students_all',
            ttl: now()->addHours(24),
            callback: function () use ($request): array {
                return $this->sharedService->query(
                    request: $request,
                    entityName: 'Student',
                    modelName: 'Student',
                    columnSearch: ['id', 'student_code', 'document_number', 'full_name', 'gender', 'email', 'phone', 'address', 'admission_mode', 'program', 'campus', 'modality', 'shift', 'status', 'graduation_year'],
                );
            }
        );
         return response()->json(new GetAllCollection(
            resource: StudentResource::collection($query['collection']),
            total: $query['total'],
            pages: $query['pages']
        ));
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

        $path = $request->file('document')->store('imports');

        // Mandamos la ruta al Job en Redis
        ProcessStudentBulkJob::dispatch($path);

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
