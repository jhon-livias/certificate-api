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
        $student = Student::query()
            ->where('document_number', $code)
            ->orWhere('student_code', $code)
            ->firstOrFail();

        return response()->json(new StudentResource($student));
    }

    public function students(GetAllRequest $request): JsonResponse
    {
        $query = $this->sharedService->query(
            request: $request,
            entityName: 'Student',
            modelName: 'Student',
            columnSearch: [
                'id',
                'student_code',
                'document_number',
                'full_name',
                'program',
                'modality',
                'faculty',
                'academic_cycle',
                'current_semester',
                'email',
                'status',
            ],
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
                'mimes:xlsx,csv,txt',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain,application/csv,application/excel,application/vnd.ms-excel,application/vnd.msexcel'
            ]
        ]);

        $path = $request->file('document')->store('imports');

        if (app()->environment('local')) {
            ProcessStudentBulkJob::dispatchSync($path);
        } else {
            ProcessStudentBulkJob::dispatch($path);
        }

        return response()->json([
            'message' => app()->environment('local')
                ? 'La carga masiva de estudiantes se procesó correctamente.'
                : 'La carga masiva de estudiantes ha comenzado en segundo plano.'
        ], 202);
    }
}
