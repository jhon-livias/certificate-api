<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Student\Models\Certificate;
use App\Student\Services\CertificateCodeService;
use App\Student\Services\WordDocumentService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function __construct(
        protected CertificateCodeService $certificateCodeService,
        protected WordDocumentService $wordDocumentService,
    ) {
    }

    public function index()
    {
        $templates = Certificate::orderBy('creation_time', 'desc')->get()->map(function ($tpl) {
            return [
                'id' => $tpl->id,
                'name' => $tpl->name,
                'code' => $tpl->code,
                'nextCode' => $this->certificateCodeService->nextCode($tpl),
                'fileName' => $tpl->file_name,
                'updatedAt' => $tpl->last_modification_time ? $tpl->last_modification_time->diffForHumans() : 'Hace un momento',
            ];
        });

        return response()->json($templates);
    }

    public function nextCode(Certificate $certificate)
    {
        return response()->json([
            'nextCode' => $this->certificateCodeService->nextCode($certificate),
        ]);
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
            'code' => strtoupper($request->code),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path
        ]);

        return response()->json([
            'message' => 'Plantilla guardada correctamente.',
            'data' => $certificate
        ], 201);
    }

    public function update(Request $request, Certificate $certificate)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'document' => [
                'nullable',
                'file',
                'mimes:docx',
                'mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ]
        ]);

        $certificate->name = $request->name;
        $certificate->code = strtoupper($request->code);

        if ($request->hasFile('document')) {
            if (Storage::exists($certificate->file_path)) {
                Storage::delete($certificate->file_path);
            }

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

        return Storage::download($certificate->file_path, $certificate->file_name);
    }

    public function preview(Certificate $certificate)
    {
        if (!Storage::exists($certificate->file_path)) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        $nextCode = $this->certificateCodeService->nextCode($certificate);
        $previewPath = $this->wordDocumentService->buildPreview(
            Storage::path($certificate->file_path),
            $nextCode,
        );

        return Storage::download(
            $previewPath,
            'preview_' . $certificate->file_name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        );
    }

    public function destroy(Certificate $certificate)
    {
        // 1. Verificamos si el archivo físico existe en el servidor y lo borramos
        if (Storage::exists($certificate->file_path)) {
            Storage::delete($certificate->file_path);
        }

        // 2. Borramos el registro de la base de datos de PostgreSQL
        $certificate->delete();

        // 3. Le respondemos a Angular que todo salió bien
        return response()->json([
            'message' => 'Plantilla y archivo eliminados correctamente.'
        ]);
    }
}
