<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Student\Jobs\ProcessCertificateJob;
use App\Student\Models\Certificate;
use App\Student\Models\Student;
use Illuminate\Support\Facades\Storage;
use function Illuminate\Support\defer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function index()
    {
        $templates = Certificate::orderBy('creation_time', 'desc')->get()->map(function ($tpl) {
            return [
                'id' => $tpl->id,
                'name' => $tpl->name,
                'code' => $tpl->code,
                'fileName' => $tpl->file_name,
                'updatedAt' => $tpl->last_modification_time ? $tpl->last_modification_time->diffForHumans() : 'Hace un momento'
            ];
        });

        return response()->json($templates);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'document' => [
                'required',
                'file',
                'mimes:docx',
                'mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ]
        ]);

        $file = $request->file('document');
        $path = $file->store('templates');

        $certificate = Certificate::create([
            'name' => $request->name,
            'code' => strtoupper($request->code), // Mantenemos mayúsculas por orden
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path
        ]);

        return response()->json([
            'message' => 'Plantilla guardada correctamente.',
            'data' => $certificate
        ], 201);
    }

    // Actualizar plantilla y/o reemplazar archivo
    public function update(Request $request, Certificate $certificate)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'document' => [
                'nullable', // Es opcional porque puede que solo quiera editar el nombre/código
                'file',
                'mimes:docx',
                'mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ]
        ]);

        $certificate->name = $request->name;
        $certificate->code = strtoupper($request->code);

        // Si el usuario subió un nuevo archivo para reemplazar el viejo
        if ($request->hasFile('document')) {
            // 1. Borramos el archivo viejo del servidor
            if (Storage::exists($certificate->file_path)) {
                Storage::delete($certificate->file_path);
            }

            // 2. Guardamos el nuevo archivo
            $file = $request->file('document');
            $certificate->file_path = $file->store('templates');
            $certificate->file_name = $file->getClientOriginalName();
        }

        $certificate->save();

        return response()->json([
            'message' => 'Plantilla actualizada correctamente.',
            'data' => $certificate
        ]);
    }

    public function download(Certificate $certificate)
    {
        if (!Storage::exists($certificate->file_path)) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        // Devuelve el archivo físico
        return Storage::download($certificate->file_path, $certificate->file_name);
    }

    // 2. Generación Individual desde el Menú 3
    public function generateSingle(Request $request): JsonResponse
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
    public function verify(string $trackingCode): JsonResponse
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
