<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Shared\Foundation\Requests\GetAllRequest;
use App\Shared\Foundation\Resources\GetAllCollection;
use App\Shared\Foundation\Services\SharedService;
use App\Student\Jobs\ProcessStudentBulkJob;
use App\Student\Models\Student;
use App\Student\Resources\StudentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(
        protected SharedService $sharedService,
    ) {
    }

    public function student(string $code): JsonResponse
    {
        $student = Student::where('student_code', $code)->firstOrFail();;
        return response()->json(new StudentResource($student));
    }

    public function students(GetAllRequest $request): JsonResponse
    {
        $query = $this->sharedService->query(
            request: $request,
            entityName: 'Student',
            modelName: 'Student',
            columnSearch: ['id', 'student_code', 'document_number', 'full_name', 'gender', 'email', 'phone', 'address', 'admission_mode', 'program', 'campus', 'modality', 'shift', 'status', 'graduation_year'],
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
}
