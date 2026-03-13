<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Student\Models\Certificate;
use Illuminate\Support\Facades\Storage;
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
}
